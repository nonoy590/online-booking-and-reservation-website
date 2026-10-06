<?php
require_once 'includes/config.php';
require_once 'includes/gcash.php';

// Works even if includes/gcash.php is an older copy without gcash_log().
if (!function_exists('gcash_log')) {
    function gcash_log(string $message): void {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        @file_put_contents($dir . '/gcash_upload.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}

$db = getDB();

/**
 * Normalize reservation dates into one consistent half-open range:
 * [check_in, check_out)
 *
 * Day Use and Exclusive Resort are single-day bookings. Older records may
 * have a NULL, equal, or earlier checkout, so those are treated as one day.
 */
function normalizeReservationRange($check_in, $check_out)
{
    $start = trim((string)$check_in);
    $end   = trim((string)$check_out);

    if (!$start || strtotime($start) === false) {
        return [null, null];
    }

    $start = date('Y-m-d', strtotime($start));

    if (!$end || strtotime($end) === false || $end <= $start) {
        $end = date('Y-m-d', strtotime($start . ' +1 day'));
    } else {
        $end = date('Y-m-d', strtotime($end));
    }

    return [$start, $end];
}

/**
 * Returns true when two normalized reservation ranges overlap.
 * Checkout on the same date as another check-in is allowed.
 */
function reservationRangesOverlap($startA, $endA, $startB, $endB)
{
    return ($startA < $endB && $endA > $startB);
}

function isExclusiveBookingType($booking_type)
{
    return in_array(
        strtolower(trim((string)$booking_type)),
        ['exclusive', 'exclusive resort'],
        true
    );
}

/**
 * Count active reservations that overlap the requested range.
 *
 * If $cottage_id is supplied, the query checks that accommodation only.
 * Exclusive Resort reservations are intentionally handled separately because
 * they have no cottage_id and reserve the entire resort.
 */
function countOverlappingReservations($db, $check_in, $check_out, $cottage_id = null)
{
    [$start, $end] = normalizeReservationRange($check_in, $check_out);

    if (!$start || !$end) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*)
        FROM reservations
        WHERE LOWER(TRIM(status)) NOT IN ('cancelled', 'rejected')
          AND check_in < ?
          AND (
                CASE
                    WHEN check_out IS NULL OR check_out <= check_in
                    THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                    ELSE check_out
                END
              ) > ?
    ";

    $params = [$end, $start];

    if ($cottage_id !== null) {
        $sql .= " AND cottage_id = ? ";
        $params[] = (int)$cottage_id;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

/**
 * Returns true when an Exclusive Resort booking overlaps the requested range.
 * Exclusive and Exclusive Resort are both recognized for legacy records.
 */
function hasExclusiveConflict($db, $check_in, $check_out)
{
    [$start, $end] = normalizeReservationRange($check_in, $check_out);

    if (!$start || !$end) {
        return false;
    }

    // IMPORTANT: use a case-insensitive status check and normalize old
    // one-day records whose checkout is NULL/equal/earlier than check-in.
    // Exclusive Resort is identified by booking_type.  The cottage_id IS
    // NULL fallback also catches older Exclusive records that were saved
    // before the booking-type label was standardized.
    $stmt = $db->prepare("
        SELECT 1
        FROM reservations
        WHERE LOWER(TRIM(COALESCE(status, ''))) NOT IN ('cancelled', 'rejected')
          AND (
                LOWER(TRIM(COALESCE(booking_type, ''))) IN ('exclusive', 'exclusive resort')
                OR (
                    cottage_id IS NULL
                    AND LOWER(TRIM(COALESCE(booking_type, ''))) = ''
                )
              )
          AND check_in < ?
          AND (
                CASE
                    WHEN check_out IS NULL OR check_out <= check_in
                    THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                    ELSE check_out
                END
              ) > ?
        LIMIT 1
    ");

    $stmt->execute([$end, $start]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Returns true when ANY active reservation overlaps the requested range.
 * Used when creating Exclusive Resort because Exclusive requires the
 * entire resort to be free.
 */
function hasAnyReservationConflict($db, $check_in, $check_out)
{
    return countOverlappingReservations($db, $check_in, $check_out) > 0;
}

/**
 * Final blocking rule for Day Use / Overnight.
 * A normal reservation is blocked by either:
 *   1) an Exclusive Resort reservation anywhere in the resort, OR
 *   2) another active reservation for the exact same accommodation.
 * This function deliberately does NOT depend on the UI availability state.
 */
function hasAccommodationConflict($db, $cottage_id, $check_in, $check_out)
{
    [$start, $end] = normalizeReservationRange($check_in, $check_out);

    if (!$start || !$end || !$cottage_id) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM reservations r
        WHERE LOWER(TRIM(COALESCE(r.status, ''))) NOT IN ('cancelled', 'rejected')
          AND r.check_in < ?
          AND (
                CASE
                    WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                    THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                    ELSE r.check_out
                END
              ) > ?
          AND (
                r.cottage_id = ?
                OR LOWER(TRIM(COALESCE(r.booking_type, ''))) IN ('exclusive', 'exclusive resort')
                OR (
                    r.cottage_id IS NULL
                    AND LOWER(TRIM(COALESCE(r.booking_type, ''))) = ''
                )
              )
        LIMIT 1
    ");

    $stmt->execute([$end, $start, (int)$cottage_id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * AUTHORITATIVE reservation conflict check.
 *
 * Rules:
 * - Every reservation except Cancelled/Rejected is ACTIVE.
 * - Exclusive/Exclusive Resort reserves the whole resort.
 * - A new Exclusive booking is blocked by ANY active overlapping reservation.
 * - A Day Use/Overnight booking is blocked by an overlapping Exclusive OR
 *   an overlapping reservation for the same cottage/room/pavilion.
 * - Date comparison is half-open: [check_in, check_out).
 */
function hasStrictReservationConflict($db, $booking_type, $cottage_id, $check_in, $check_out)
{
    [$start, $end] = normalizeReservationRange($check_in, $check_out);

    if (!$start || !$end) {
        return true;
    }

    $exclusive = isExclusiveBookingType($booking_type);

    if ($exclusive) {
        $sql = "
            SELECT 1
            FROM reservations r
            WHERE LOWER(TRIM(COALESCE(r.status, ''))) NOT IN ('cancelled', 'rejected')
              AND r.check_in < ?
              AND (
                    CASE
                        WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                        THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                        ELSE r.check_out
                    END
                  ) > ?
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$end, $start]);
        return (bool)$stmt->fetchColumn();
    }

    if (!$cottage_id) {
        return true;
    }

    $sql = "
        SELECT 1
        FROM reservations r
        WHERE LOWER(TRIM(COALESCE(r.status, ''))) NOT IN ('cancelled', 'rejected')
          AND r.check_in < ?
          AND (
                CASE
                    WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                    THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                    ELSE r.check_out
                END
              ) > ?
          AND (
                r.cottage_id = ?
                OR LOWER(TRIM(COALESCE(r.booking_type, ''))) IN ('exclusive', 'exclusive resort')
                OR (
                    r.cottage_id IS NULL
                    AND LOWER(TRIM(COALESCE(r.booking_type, ''))) = ''
                )
              )
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$end, $start, (int)$cottage_id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Acquire one global application lock for the complete availability check
 * and INSERT. This closes the race where two users submit simultaneously.
 */
function acquireReservationLock($db, $timeout = 10)
{
    $timeout = max(1, min(30, (int)$timeout));
    $stmt = $db->query(
        "SELECT GET_LOCK('sfive_reservation_lock', {$timeout})"
    );

    return ((int)$stmt->fetchColumn() === 1);
}

function releaseReservationLock($db)
{
    try {
        $db->query("SELECT RELEASE_LOCK('sfive_reservation_lock')");
    } catch (Throwable $e) {
        // MySQL releases the advisory lock when the connection closes.
    }
}

$gcash_settings = getGcashSettings($db);
$gcash_qr_url = !empty($gcash_settings['qr_image']) && file_exists(__DIR__ . '/uploads/gcash/' . $gcash_settings['qr_image'])
    ? 'uploads/gcash/' . $gcash_settings['qr_image'] . '?v=' . filemtime(__DIR__ . '/uploads/gcash/' . $gcash_settings['qr_image'])
    : '';


if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'check_availability') {

        $ci = isset($_GET['check_in'])  ? clean($_GET['check_in'])  : '';
        $co = isset($_GET['check_out']) ? clean($_GET['check_out']) : '';

        if (
            !$ci ||
            !$co ||
            strtotime($ci) === false ||
            strtotime($co) === false ||
            $ci >= $co
        ) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid date range'
            ]);
            exit;
        }

        /*
         * IMPORTANT:
         * All availability uses the same half-open date range:
         * [check_in, check_out)
         *
         * Therefore:
         * - Exclusive on Sept 28 => blocks Sept 28 only.
         * - Day Use on Sept 28 => conflicts with that Exclusive booking.
         * - Overnight Sept 28 -> Sept 29 => conflicts.
         * - A booking beginning Sept 29 does NOT conflict with Sept 28.
         */

        $stmt = $db->prepare("
            SELECT
                c.id,

                (
                    SELECT COUNT(*)
                    FROM reservations r
                    WHERE r.cottage_id = c.id
                      AND r.status NOT IN ('Cancelled','Rejected')
                      AND r.check_in < ?
                      AND (
                            CASE
                                WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                                THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                                ELSE r.check_out
                            END
                          ) > ?
                ) AS is_booked,

                (
                    SELECT COUNT(*)
                    FROM reservations er
                    WHERE LOWER(TRIM(er.booking_type)) IN ('exclusive', 'exclusive resort')
                      AND er.status NOT IN ('Cancelled','Rejected')
                      AND er.check_in < ?
                      AND (
                            CASE
                                WHEN er.check_out IS NULL OR er.check_out <= er.check_in
                                THEN DATE_ADD(er.check_in, INTERVAL 1 DAY)
                                ELSE er.check_out
                            END
                          ) > ?
                ) AS is_exclusive

            FROM cottages c
            WHERE c.is_available = 1
        ");

        $stmt->execute([
            $co,
            $ci,
            $co,
            $ci
        ]);

        $availability = [];

        foreach ($stmt->fetchAll() as $row) {

            if ((int)$row['is_exclusive'] > 0) {
                $availability[$row['id']] = 'booked';
            } elseif ((int)$row['is_booked'] > 0) {
                $availability[$row['id']] = 'booked';
            } else {
                $availability[$row['id']] = 'available';
            }
        }

        // One Exclusive Resort reservation blocks the entire resort.
        $exclusive_active = hasExclusiveConflict(
            $db,
            $ci,
            $co
        );

        if ($exclusive_active) {
            foreach ($availability as $id => $status) {
                $availability[$id] = 'booked';
            }
        }

        echo json_encode([
            'success' => true,
            'availability' => $availability,
            'exclusive_active' => $exclusive_active
        ]);

        exit;
    }


    if ($_GET['action'] === 'get_booked_dates') {

        $cottage_id  = isset($_GET['cottage_id']) ? (int)$_GET['cottage_id'] : 0;
        $range_start = date('Y-m-d');
        $range_end   = date('Y-m-d', strtotime('+12 months'));

        if ($cottage_id) {

            $stmt = $db->prepare("
                SELECT check_in, check_out
                FROM reservations
                WHERE cottage_id = ?
                AND status NOT IN ('Cancelled','Rejected')
                AND (
                    CASE
                        WHEN check_out IS NULL OR check_out <= check_in
                        THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                        ELSE check_out
                    END
                ) > ?
            ");

            $stmt->execute([
                $cottage_id,
                $range_start
            ]);

            $ranges = $stmt->fetchAll();

            $exclusive_stmt = $db->prepare("
                SELECT
                    check_in,
                    CASE
                        WHEN check_out IS NULL OR check_out <= check_in
                        THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                        ELSE check_out
                    END AS check_out
                FROM reservations
                WHERE LOWER(TRIM(booking_type)) IN ('exclusive', 'exclusive resort')
                  AND status NOT IN ('Cancelled','Rejected')
                  AND (
                        CASE
                            WHEN check_out IS NULL OR check_out <= check_in
                            THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                            ELSE check_out
                        END
                      ) > ?
            ");

            $exclusive_stmt->execute([
                $range_start
            ]);

            foreach ($exclusive_stmt->fetchAll() as $exclusive_range) {
                $ranges[] = $exclusive_range;
            }

            echo json_encode([
                'success' => true,
                'mode' => 'cottage',
                'ranges' => $ranges
            ]);

            exit;
        }


        // ----------------------------------------------------
        // No specific cottage chosen:
        // Find dates where EVERY cottage is taken.
        // Exclusive reservations automatically make the
        // entire resort unavailable.
        // ----------------------------------------------------
        $total_cottages = (int)$db->query("
            SELECT COUNT(*)
            FROM cottages
            WHERE is_available = 1
        ")->fetchColumn();

        if ($total_cottages === 0) {
            echo json_encode([
                'success' => true,
                'mode' => 'resort',
                'fully_booked_dates' => []
            ]);

            exit;
        }


        $stmt = $db->prepare("
            SELECT cottage_id, check_in, check_out, booking_type
            FROM reservations
            WHERE LOWER(TRIM(status)) NOT IN ('cancelled','rejected')
            AND (
                check_out > ?
                OR check_out IS NULL
                OR check_out <= check_in
            )
        ");

        $stmt->execute([
            $range_start
        ]);

        $day_booked_cottages = [];

        foreach ($stmt->fetchAll() as $r) {

            // Exclusive Resort is a resort-wide reservation.
            // It does not need a cottage_id because it reserves EVERY
            // cottage, room, and pavilion for the selected date(s).
            if (in_array(
                $r['booking_type'] ?? '',
                ['Exclusive', 'Exclusive Resort'],
                true
            )) {
                continue;
            }

            $d = new DateTime(max($r['check_in'], $range_start));
            $end = new DateTime(min($r['check_out'], $range_end));

            while ($d < $end) {

                $day_booked_cottages[$d->format('Y-m-d')][$r['cottage_id']] = true;

                $d->modify('+1 day');
            }
        }


        $exclusive_stmt = $db->prepare("
            SELECT
                check_in,
                CASE
                    WHEN check_out IS NULL OR check_out <= check_in
                    THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                    ELSE check_out
                END AS check_out
            FROM reservations
            WHERE LOWER(TRIM(booking_type)) IN ('exclusive', 'exclusive resort')
              AND status NOT IN ('Cancelled','Rejected')
              AND (
                    CASE
                        WHEN check_out IS NULL OR check_out <= check_in
                        THEN DATE_ADD(check_in, INTERVAL 1 DAY)
                        ELSE check_out
                    END
                  ) > ?
        ");

        $exclusive_stmt->execute([
            $range_start
        ]);

        $exclusive_dates = [];

        foreach ($exclusive_stmt->fetchAll() as $r) {

            $d = new DateTime(max($r['check_in'], $range_start));
            $end = new DateTime(min($r['check_out'], $range_end));

            while ($d < $end) {

                $exclusive_dates[] = $d->format('Y-m-d');

                $d->modify('+1 day');
            }
        }


        $fully_booked = [];

        foreach ($day_booked_cottages as $day => $cottages) {

            if (count($cottages) >= $total_cottages) {
                $fully_booked[] = $day;
            }
        }


        foreach ($exclusive_dates as $day) {
            if (!in_array($day, $fully_booked, true)) {
                $fully_booked[] = $day;
            }
        }

        sort($fully_booked);

        echo json_encode([
            'success' => true,
            'mode' => 'resort',
            'fully_booked_dates' => $fully_booked
        ]);

        exit;
    }


    echo json_encode([
        'success' => false,
        'error' => 'Unknown action'
    ]);

    exit;
}


$errors = [];
$success = false;
$booking_result = null;


$check_in = isset($_GET['check_in'])
    ? clean($_GET['check_in'])
    : '';

$check_out = isset($_GET['check_out'])
    ? clean($_GET['check_out'])
    : '';

$guests = isset($_GET['guests'])
    ? (int)$_GET['guests']
    : 2;

$cottage_id = isset($_GET['cottage_id'])
    ? (int)$_GET['cottage_id']
    : 0;

$event_type_val = isset($_GET['event_type'])
    ? clean($_GET['event_type'])
    : ($_POST['event_type'] ?? '');


$booking_type_val = isset($_GET['booking_type'])
    ? clean($_GET['booking_type'])
    : ($_POST['booking_type'] ?? 'Day Use');

if (!in_array(
    $booking_type_val,
    ['Day Use', 'Overnight', 'Exclusive'],
    true
)) {
    $booking_type_val = 'Day Use';
}


// Day Use is exactly one day
if (
    $booking_type_val === 'Day Use' &&
    $check_in &&
    strtotime($check_in) !== false
) {
    $check_out = date(
        'Y-m-d',
        strtotime($check_in) + 86400
    );
}


function resolveUnitPrice($cottage, $event_type, $event_prices)
{
    if (!$cottage) {
        return 0;
    }

    if (
        $cottage['category'] === 'Pavilion' &&
        $event_type &&
        isset($event_prices[$cottage['id']][$event_type])
    ) {
        return (float)$event_prices[$cottage['id']][$event_type];
    }

    return (float)$cottage['price_per_night'];
}


function getCottagesWithStatus(
    $db,
    $check_in,
    $check_out
) {

    if ($check_in && $check_out) {

        $stmt = $db->prepare("
            SELECT c.*,

                (
                    SELECT COUNT(*)
                    FROM reservations r
                    WHERE r.cottage_id = c.id
                    AND r.status NOT IN ('Cancelled','Rejected')
                    AND NOT (
                        r.check_out <= ?
                        OR r.check_in >= ?
                    )
                ) AS is_booked,

                (
                    SELECT COUNT(*)
                    FROM reservations er
                    WHERE LOWER(TRIM(er.booking_type)) IN ('exclusive', 'exclusive resort')
                    AND er.status NOT IN ('Cancelled','Rejected')
                    AND er.check_in < ?
                    AND (
                        CASE
                            WHEN er.check_out IS NULL OR er.check_out <= er.check_in
                            THEN DATE_ADD(er.check_in, INTERVAL 1 DAY)
                            ELSE er.check_out
                        END
                    ) > ?
                ) AS is_exclusive

            FROM cottages c

            WHERE c.is_available = 1

            ORDER BY FIELD(
                c.category,
                'Cottage - Kids Pool',
                'Cottage - Adult Pool',
                'Rooms',
                'Pavilion'
            ),
            c.name
        ");

        // Normal accommodation overlap uses [check_in, check_out).
        // Exclusive Resort uses the same rule, so its parameters must be
        // [requested check_out, requested check_in].
        $stmt->execute([
            $check_in,
            $check_out,
            $check_out,
            $check_in
        ]);

    } else {

        $stmt = $db->query("
            SELECT c.*,
                0 AS is_booked,
                0 AS is_exclusive

            FROM cottages c

            WHERE c.is_available = 1

            ORDER BY FIELD(
                c.category,
                'Cottage - Kids Pool',
                'Cottage - Adult Pool',
                'Rooms',
                'Pavilion'
            ),
            c.name
        ");
    }

    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {

        if (
            isset($row['is_exclusive']) &&
            (int)$row['is_exclusive'] > 0
        ) {
            $row['is_booked'] = 1;
        }
    }

    return $rows;
}


$all_cottages = getCottagesWithStatus(
    $db,
    $check_in,
    $check_out
);


$pavilion_event_prices = getPavilionEventPrices($db);


$cottage_thumbs = [];

try {

    $thumb_stmt = $db->query("
        SELECT cottage_id, filename
        FROM cottage_images ci
        WHERE sort_order = (
            SELECT MIN(sort_order)
            FROM cottage_images
            WHERE cottage_id = ci.cottage_id
        )
    ");

    foreach ($thumb_stmt->fetchAll() as $row) {

        if (!isset($cottage_thumbs[$row['cottage_id']])) {

            $cottage_thumbs[$row['cottage_id']] =
                'uploads/cottages/' . $row['filename'];
        }
    }

} catch (Exception $e) {
    // table may not exist
}


$selected_cottage = null;

if ($cottage_id) {

    $stmt = $db->prepare("
        SELECT *
        FROM cottages
        WHERE id = ?
    ");

    $stmt->execute([
        $cottage_id
    ]);

    $selected_cottage = $stmt->fetch();
}


$nights = 0;
$total_price = 0;
$selected_unit_price = 0;

if (
    $check_in &&
    $check_out &&
    $selected_cottage
) {

    $nights =
        (strtotime($check_out) - strtotime($check_in))
        / 86400;

    $selected_unit_price =
        resolveUnitPrice(
            $selected_cottage,
            $event_type_val,
            $pavilion_event_prices
        );

    $total_price =
        $nights * $selected_unit_price;
}

// Exclusive Resort has a fixed total booking fee.
if ($booking_type_val === 'Exclusive') {
    $selected_unit_price = 40000;
    $total_price = 40000;

}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $guest_name =
        clean($_POST['guest_name'] ?? '');

    $guest_email =
        clean($_POST['guest_email'] ?? '');

    $guest_phone =
        clean($_POST['guest_phone'] ?? '');

    $cottage_id =
        (int)($_POST['cottage_id'] ?? 0);

    $check_in =
        clean($_POST['check_in'] ?? '');

    $check_out =
        clean($_POST['check_out'] ?? '');

    $num_guests =
        (int)($_POST['num_guests'] ?? 1);

    $special_req =
        clean($_POST['special_requests'] ?? '');

    $payment_method =
        clean($_POST['payment_method'] ?? 'Pay on Arrival');

    $event_type =
        clean($_POST['event_type'] ?? '');


    $booking_type =
        trim(clean($_POST['booking_type'] ?? 'Day Use'));

    // Normalize booking-type spelling/capitalization so the conflict rules
    // cannot be bypassed by a different case or extra spaces.
    $booking_type_key = strtolower(preg_replace('/\s+/', ' ', $booking_type));

    if ($booking_type_key === 'exclusive resort') {
        $booking_type = 'Exclusive';
    } elseif ($booking_type_key === 'exclusive') {
        $booking_type = 'Exclusive';
    } elseif ($booking_type_key === 'overnight') {
        $booking_type = 'Overnight';
    } elseif ($booking_type_key === 'day use') {
        $booking_type = 'Day Use';
    }


    // Accept both the current value and the legacy/admin label.
    if ($booking_type === 'Exclusive Resort') {
        $booking_type = 'Exclusive';
    }

    // Exclusive Resort: GCash only. The guest chooses to pay in full or the
    // down payment now; every other booking type always pays in full.
    $payment_option =
        (($_POST['payment_option'] ?? 'Downpayment') === 'Full')
            ? 'Full'
            : 'Downpayment';

    if ($booking_type === 'Exclusive') {
        $payment_method = 'GCash';
    } else {
        $payment_option = 'Full';
    }

    if (!in_array(
        $booking_type,
        ['Day Use', 'Overnight', 'Exclusive'],
        true
    )) {
        $booking_type = 'Day Use';
    }


    if (
        $booking_type === 'Day Use' &&
        $check_in &&
        strtotime($check_in) !== false
    ) {

        $check_out = date(
            'Y-m-d',
            strtotime($check_in) + 86400
        );
    }


    // --------------------------------------------------------
    // EXCLUSIVE RESORT (single event date)
    // Keep an internal next-day checkout for availability and database overlap checks,
    // but do not display it to the guest.
    // --------------------------------------------------------

    if (
        $booking_type === 'Exclusive' &&
        $check_in &&
        strtotime($check_in) !== false
    ) {
        $check_out = date(
            'Y-m-d',
            strtotime($check_in) + 86400
        );
    }


    // --------------------------------------------------------
    // FINAL DATE NORMALIZATION BEFORE ALL CONFLICT CHECKS
    // --------------------------------------------------------
    [$normalized_check_in, $normalized_check_out] =
        normalizeReservationRange($check_in, $check_out);

    if ($normalized_check_in && $normalized_check_out) {
        $check_in = $normalized_check_in;
        $check_out = $normalized_check_out;
    }


    $gcash_reference =
        clean($_POST['gcash_reference'] ?? '');

    $gcash_sender_name =
        clean($_POST['gcash_sender_name'] ?? '');

    $gcash_sender_phone =
        clean($_POST['gcash_sender_phone'] ?? '');


    if (!$guest_name) {
        $errors[] =
            "Full name is required.";
    }


    if (!$guest_email) {

        $errors[] =
            "Email address is required.";

    } elseif (
        !filter_var(
            $guest_email,
            FILTER_VALIDATE_EMAIL
        )
        ||
        !preg_match(
            '/^[a-zA-Z0-9._%+-]+@gmail\.com$/i',
            $guest_email
        )
    ) {

        $errors[] =
            "Please enter a valid Gmail address (e.g. yourname@gmail.com).";
    }


    if (!$guest_phone) {

        $errors[] =
            "Phone number is required.";

    } elseif (
        !preg_match(
            '/^[0-9]{11}$/',
            $guest_phone
        )
    ) {

        $errors[] =
            "Phone number must be exactly 11 digits (e.g. 09171234567).";
    }


    // --------------------------------------------------------
    // Cottage is NOT required for Exclusive
    // --------------------------------------------------------

    if (
        !$cottage_id &&
        $booking_type !== 'Exclusive'
    ) {

        $errors[] =
            "Please select a cottage.";
    }


    if (!$check_in) {

        $errors[] =
            "Please choose a date.";

    } elseif (
        $booking_type === 'Overnight' &&
        !$check_out
    ) {

        $errors[] =
            "Check-out date is required for overnight stays.";
    }


    if (
        $check_in &&
        $check_out &&
        $check_in >= $check_out
    ) {

        $errors[] =
            "Check-out must be after check-in.";
    }


    if (
        $check_in &&
        $check_in < date('Y-m-d')
    ) {

        $errors[] =
            "Check-in cannot be in the past.";
    }


    $selected_category = '';

    if ($cottage_id) {

        $cat_stmt = $db->prepare("
            SELECT category
            FROM cottages
            WHERE id = ?
        ");

        $cat_stmt->execute([
            $cottage_id
        ]);

        $selected_category =
            $cat_stmt->fetchColumn();
    }


    // --------------------------------------------------------
    // Overnight = Rooms only
    // --------------------------------------------------------

    if (
        $booking_type === 'Overnight' &&
        $selected_category &&
        $selected_category !== OVERNIGHT_CATEGORY
    ) {

        $errors[] =
            "Overnight stays are only available for Rooms. Please switch to Day Use for this accommodation, or pick a Room for an overnight stay.";
    }


    $valid_event_types = [
        'Party Event',
        'Birthday Event',
        'Marriage Event'
    ];


    if ($selected_category === 'Pavilion') {

        if (
            !in_array(
                $event_type,
                $valid_event_types,
                true
            )
        ) {

            $errors[] =
                "Please select an event type (Party, Birthday, or Marriage) for the Pavilion.";
        }

    } else {

        $event_type = null;
    }


    if ($booking_type === 'Exclusive') {

        if ($num_guests > 200) {

            $errors[] =
                "Exclusive Resort bookings can accommodate a maximum of 200 guests.";
        }

        if ($num_guests < 1) {

            $errors[] =
                "Please enter at least 1 guest.";
        }

        $cottage_id = 0;

        if (!$event_type) {

            // Keep event optional here because your existing
            // event_type system is specifically for Pavilion.
        }
    }


    if ($payment_method === 'GCash') {

        if (!$gcash_reference) {

            $errors[] =
                "GCash reference number is required.";
        }

        if (!$gcash_sender_name) {

            $errors[] =
                "GCash sender name is required.";
        }


        if (
            empty($_FILES['gcash_proof'])
            ||
            $_FILES['gcash_proof']['error']
            === UPLOAD_ERR_NO_FILE
        ) {

            $errors[] =
                "Please upload a screenshot of your GCash payment as proof.";

        } elseif (
            $_FILES['gcash_proof']['error']
            !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                "There was a problem uploading your screenshot. Please try again.";

        } elseif (
            !in_array(
                @mime_content_type(
                    $_FILES['gcash_proof']['tmp_name']
                ),
                [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp'
                ]
            )
        ) {

            $errors[] =
                "Screenshot must be an image file (JPG, PNG, GIF, or WEBP).";

        } elseif (
            $_FILES['gcash_proof']['size']
            > 5 * 1024 * 1024
        ) {

            $errors[] =
                "Screenshot must be smaller than 5MB.";
        }
    }


    if (
        empty($errors) &&
        $booking_type !== 'Exclusive' &&
        $cottage_id
    ) {

        $stmt = $db->prepare("
            SELECT capacity
            FROM cottages
            WHERE id = ?
        ");

        $stmt->execute([
            $cottage_id
        ]);

        $max_cap =
            (int)$stmt->fetchColumn();


        if ($num_guests > $max_cap) {

            $errors[] =
                "The selected cottage can only accommodate up to {$max_cap} guests. You entered {$num_guests}.";
        }
    }


    if (
        empty($errors) &&
        $booking_type === 'Exclusive'
    ) {

        if ($num_guests > 200) {

            $errors[] =
                "The maximum capacity for an Exclusive Resort booking is 200 guests.";
        }
    }


    // ========================================================
    // FINAL DOUBLE-BOOKING CHECK
    //
    // IMPORTANT: this check is protected by a MySQL advisory lock.
    // This prevents two users from submitting the same cottage/date at
    // the same moment and both passing the SELECT check.
    // ========================================================

    $reservation_lock_acquired = false;

    if (empty($errors)) {

        $reservation_lock_acquired = acquireReservationLock($db);

        if (!$reservation_lock_acquired) {
            $errors[] =
                "The booking system is busy processing another reservation. Please try again in a few seconds.";
        }
    }

    if (empty($errors) && $reservation_lock_acquired) {

        /*
         * FINAL SERVER-SIDE CONFLICT RULES
         * --------------------------------
         * 1. Exclusive Resort = reserves the whole resort. Therefore it can
         *    only be inserted when ZERO active reservations overlap.
         * 2. Day Use / Overnight = reserves the selected accommodation.
         *    They cannot overlap another active reservation for that same
         *    accommodation, and they cannot overlap Exclusive Resort.
         * 3. Cancelled and Rejected reservations are ignored.
         * 4. Same-day bookings use [check_in, check_out), so a booking ending
         *    on the next booking's check-in date is not a double booking.
         */
        // NEVER rely on the browser's Occupied/Available state.
        // The database is checked again immediately before INSERT.
        // THIS IS THE FINAL AUTHORITY BEFORE INSERT.
        // Never trust the browser availability state.
        if (hasStrictReservationConflict(
            $db,
            $booking_type,
            $cottage_id,
            $check_in,
            $check_out
        )) {

            if (isExclusiveBookingType($booking_type)) {
                $errors[] =
                    "Reservation rejected: the selected date is already occupied. Exclusive Resort requires the entire resort to be free.";
            } else {
                $errors[] =
                    "Reservation rejected: the selected date is already occupied. Day Use and Overnight cannot be double-booked, and an Exclusive Resort blocks the entire resort.";
            }
        }
    }

    // If the final check failed, release the lock immediately.
    if (!empty($errors) && $reservation_lock_acquired) {
        releaseReservationLock($db);
        $reservation_lock_acquired = false;
    }


    if (empty($errors)) {

        $cottage = null;

        if (isExclusiveBookingType($booking_type)) {

            // Exclusive Resort is one reservation that owns the whole
            // resort inventory (all Cottages, Rooms, and Pavilion).
            // Keep cottage_id NULL; availability treats this reservation
            // as a conflict for every accommodation.
            $cottage_id_for_db = null;

            // Exclusive Resort uses a fixed Exclusive Resort rental price of ₱40,000.
            $nights = (
                strtotime($check_out)
                - strtotime($check_in)
            ) / 86400;

            $unit_price = 40000;

            $total_price = 40000;

            $cottage_name = 'Exclusive Resort';

        } else {

            $stmt = $db->prepare("
                SELECT *
                FROM cottages
                WHERE id = ?
            ");

            $stmt->execute([
                $cottage_id
            ]);

            $cottage = $stmt->fetch();


            $nights = (
                strtotime($check_out)
                - strtotime($check_in)
            ) / 86400;


            $unit_price =
                resolveUnitPrice(
                    $cottage,
                    $event_type,
                    $pavilion_event_prices
                );


            $total_price =
                $nights * $unit_price;


            $cottage_id_for_db =
                $cottage_id;


            $cottage_name =
                $cottage['name'];
        }


        $booking_code =
            generateBookingCode();


        $pay_status =
            ($payment_method === 'GCash')
            ? 'Pending Verification'
            : 'Unpaid';

        // Amount due now is always computed on the server, never from the form.
        $payment_type     = 'Full';
        $pay_now          = (float)$total_price;
        $balance_due_date = null;

        if (isExclusiveBookingType($booking_type) && $payment_option === 'Downpayment') {
            $payment_type     = 'Downpayment';
            $pay_now          = round($total_price * EXCLUSIVE_DOWNPAYMENT_RATE, 2);
            $balance_due_date = balanceDueDate($check_in);
        }
        $balance_remaining = round($total_price - $pay_now, 2);


        $stmt = $db->prepare("
            INSERT INTO reservations
            (
                booking_code,
                guest_name,
                guest_email,
                guest_phone,
                cottage_id,
                check_in,
                check_out,
                booking_type,
                num_guests,
                special_requests,
                total_price,
                status,
                payment_method,
                payment_status,
                event_type,
                payment_option,
                balance_due_date
            )
            VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,
                'Pending',
                ?,?,?,?,?
            )
        ");


        $new_res_id = 0;

        try {
            // Keep the advisory lock until the INSERT has completed.
            // A transaction also makes the reservation write atomic.
            if (!$db->inTransaction()) {
                $db->beginTransaction();
            }

            $stmt->execute([
                $booking_code,
                $guest_name,
                $guest_email,
                $guest_phone,
                $cottage_id_for_db,
                $check_in,
                $check_out,
                $booking_type,
                $num_guests,
                $special_req,
                $total_price,
                $payment_method,
                $pay_status,
                $event_type,
                $payment_option,
                $balance_due_date
            ]);

            // lastInsertId() must be read BEFORE commit(); after commit() MySQL
            // can return 0, which made the GCash screenshot/payment step skip.
            $new_res_id = (int)$db->lastInsertId();

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($reservation_lock_acquired) {
                releaseReservationLock($db);
                $reservation_lock_acquired = false;
            }

            $errors[] =
                "The reservation could not be saved. Please try again.";
        }

        if ($reservation_lock_acquired) {
            releaseReservationLock($db);
            $reservation_lock_acquired = false;
        }


        // Only trust the id when the INSERT actually succeeded.
        $res_id = 0;
        if (empty($errors)) {
            $res_id = (int)$new_res_id;

            if (!$res_id) {
                // Fallback: find the reservation we just created by its unique code.
                $find = $db->prepare("SELECT id FROM reservations WHERE booking_code = ? LIMIT 1");
                $find->execute([$booking_code]);
                $res_id = (int)$find->fetchColumn();
            }
        }

        if (empty($errors) && !$res_id) {
            gcash_log('Could not determine reservation id for ' . $booking_code);
            $errors[] = "The reservation could not be confirmed. Please try again.";
        }


        if ($payment_method === 'GCash' && $res_id) {

            $up = $_FILES['gcash_proof'] ?? [];
            gcash_log('Booking ' . $booking_code . ' (reservation #' . $res_id . ') inserted. '
                . 'gcash_proof: error=' . ($up['error'] ?? 'missing')
                . ' size=' . ($up['size'] ?? '?')
                . ' name=' . ($up['name'] ?? '?'));

            // Safety net: if PHP stops with a fatal error after the reservation was
            // saved but before the payment proof was stored, log it and remove the
            // half-finished reservation instead of leaving it behind.
            $GLOBALS['__gcash_guard'] = ['id' => $res_id, 'code' => $booking_code, 'done' => false];
            register_shutdown_function(function () use ($db) {
                $g = $GLOBALS['__gcash_guard'] ?? null;
                if (!$g || $g['done']) { return; }
                gcash_log('SCRIPT STOPPED EARLY for ' . $g['code'] . ': ' . json_encode(error_get_last()));
                try {
                    $db->prepare("DELETE FROM reservations WHERE id = ?")->execute([$g['id']]);
                    gcash_log('Removed unfinished reservation #' . $g['id']);
                } catch (Throwable $e) {
                    gcash_log('Could not remove reservation #' . $g['id'] . ': ' . $e->getMessage());
                }
            });

            try {
                $proof_filename =
                    uploadGcashProof(
                        $_FILES['gcash_proof'],
                        $booking_code
                    );

                if (!$proof_filename) {

                    $errors[] =
                        "Your screenshot could not be saved. Please try uploading it again.";

                } else {

                    gcash_log('Screenshot saved as ' . $proof_filename);

                    // Amount and type were decided above ($pay_now / $payment_type):
                    // Full total, or the Exclusive Resort down payment.
                    $gcash_amount = $pay_now;

                    $gcash_saved = saveGcashSubmission([
                        'db'               => $db,
                        'reservation_id'   => $res_id,
                        'reference_number' => $gcash_reference,
                        'amount'           => $gcash_amount,
                        'payment_type'     => $payment_type,
                        'guest_name'       => $gcash_sender_name ?: $guest_name,
                        'guest_phone'      => $gcash_sender_phone ?: $guest_phone,
                        'proof_image'      => $proof_filename,
                    ]);

                    if (empty($gcash_saved['success'])) {
                        gcash_log('Payment row NOT saved for ' . $booking_code . ': ' . ($gcash_saved['error'] ?? 'unknown error'));
                        $errors[] =
                            "Your GCash payment details could not be saved. Please try again.";
                    } else {
                        gcash_log('Payment row saved for ' . $booking_code);
                    }
                }
            } catch (Throwable $e) {
                gcash_log('EXCEPTION for ' . $booking_code . ': ' . $e->getMessage()
                    . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
                $errors[] =
                    "Your GCash payment could not be processed. Please try again.";
            }

            $GLOBALS['__gcash_guard']['done'] = true;
        }


        if (empty($errors)) {

            $booking_result = [

                'code' =>
                    $booking_code,

                'name' =>
                    $guest_name,

                'email' =>
                    $guest_email,

                'cottage' =>
                    $cottage_name,

                'check_in' =>
                    $check_in,

                'check_out' =>
                    $check_out,

                'booking_type' =>
                    $booking_type,

                'nights' =>
                    $nights,

                'total' =>
                    $total_price,

                'payment_method' =>
                    $payment_method,

                'pay_status' =>
                    $pay_status,

                'gcash_ref' =>
                    $gcash_reference,

                'amount_paid' =>
                    $pay_now,

                'balance' =>
                    $balance_remaining,

                'balance_due' =>
                    $balance_due_date,

                'event_type' =>
                    $event_type,
            ];


            $success = true;

        } else {

            if (
                !empty($res_id) &&
                $payment_method === 'GCash'
            ) {

                $db->prepare("
                    DELETE FROM reservations
                    WHERE id = ?
                ")->execute([
                    $res_id
                ]);
            }
        }
    }
}


$grouped_cottages = [];

foreach ($all_cottages as $c) {

    $grouped_cottages[$c['category']][] =
        $c;
}


$cat_icons = [
    'Cottage - Kids Pool' => '🏊‍♀️',
    'Cottage - Adult Pool' => '🏊',
    'Rooms' => '✨',
    'Pavilion' => '🎉'
];


$check_in_val =
    $check_in
    ?: ($_POST['check_in'] ?? '');


$check_out_val =
    $check_out
    ?: ($_POST['check_out'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Book Your Stay — S-Five Inland Resort
</title>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap"
      rel="stylesheet">

<link rel="stylesheet"
      href="css/style.css">

<link rel="stylesheet"
      href="css/booking.css">

<link rel="stylesheet"
      href="css/mobile-app.css">

</head>

<body>


<nav class="navbar navbar-light navbar-sub"
     id="navbar">

    <div class="nav-container">

        <a href="javascript:history.back()"
           class="s5-back"
           aria-label="Back">
            ←
        </a>

        <span class="s5-header-title">
            Book Cottage
        </span>

        <a href="index.php"
           class="nav-logo">

            <img src="images/sfive_logo.png"
                 alt="S-Five Inland Resort"
                 class="nav-logo-img">

            <span class="logo-text">
                S-Five Inland Resort
            </span>

        </a>

        <ul class="nav-links">

            <li>
                <a href="index.php">
                    Home
                </a>
            </li>

            <li>
                <a href="index.php#cottages">
                    Cottages
                </a>
            </li>

            <li>
                <a href="index.php#about">
                    About
                </a>
            </li>

            <li>
                <a href="check_booking.php">
                    My Booking
                </a>
            </li>

        </ul>

        <button class="nav-toggle"
                id="navToggle"
                aria-expanded="false">
            ☰
        </button>

    </div>

</nav>


<?php
$active_page = 'cottages';
require 'includes/drawer.php';
?>


<div class="bw-page">

<div class="bw-wrap">


<?php if ($success && $booking_result): ?>

<div class="bw-success">

    <div class="bw-success-check">
        ✓
    </div>

    <h2>
        Booking Confirmed!
    </h2>

    <p>

        <?php if ($booking_result['payment_method'] === 'Pay on Arrival'): ?>

            Your reservation is saved as
            <strong>Pending</strong>
            — settle payment in cash or GCash when you arrive.

        <?php else: ?>

            Your GCash payment proof has been received and is
            <strong>Pending Verification</strong>
            by our team.

            <?php if (!empty($booking_result['balance']) && $booking_result['balance'] > 0): ?>
            <br>
            Down payment sent: <strong>₱<?= number_format($booking_result['amount_paid'], 2) ?></strong>.
            Remaining balance of
            <strong>₱<?= number_format($booking_result['balance'], 2) ?></strong>
            is to be paid via GCash on or before
            <strong><?= date('F j, Y', strtotime($booking_result['balance_due'])) ?></strong>
            — use <em>My Booking</em> with your booking code to pay it.
            <?php endif; ?>

        <?php endif; ?>

    </p>


    <div class="bw-ref-box">

        <div class="bw-ref-label">
            Booking Reference
        </div>

        <div class="bw-ref-code"
             id="bookingCodeText">

            <?= $booking_result['code'] ?>

        </div>

    </div>


    <div class="bw-success-summary">

        <div class="bw-review-row">
            <span>Guest Name</span>
            <strong>
                <?= htmlspecialchars($booking_result['name']) ?>
            </strong>
        </div>


        <div class="bw-review-row">
            <span>Accommodation</span>
            <strong>
                <?= htmlspecialchars($booking_result['cottage']) ?>
            </strong>
        </div>


        <?php if (!empty($booking_result['event_type'])): ?>

        <div class="bw-review-row">
            <span>Event Type</span>

            <strong>
                <?= htmlspecialchars($booking_result['event_type']) ?>
            </strong>
        </div>

        <?php endif; ?>


        <div class="bw-review-row">

            <span>
                <?= $booking_result['booking_type'] === 'Day Use'
                    ? 'Visit Date'
                    : ($booking_result['booking_type'] === 'Exclusive'
                        ? 'Event Start'
                        : 'Check-in')
                ?>
            </span>

            <strong>
                <?= date(
                    'M d, Y',
                    strtotime($booking_result['check_in'])
                ) ?>
            </strong>

        </div>


        <?php if ($booking_result['booking_type'] !== 'Exclusive'): ?>
        <div class="bw-review-row">

            <span>Check-out</span>

            <strong>
                <?= date(
                    'M d, Y',
                    strtotime($booking_result['check_out'])
                ) ?>
            </strong>

        </div>
        <?php endif; ?>


        <div class="bw-review-row">

            <span>Guests</span>

            <strong>
                <?= (int)($booking_result['booking_type'] === 'Exclusive'
                    ? $booking_result['nights'] * 0 + ($_POST['num_guests'] ?? 0)
                    : ($_POST['num_guests'] ?? 0)
                ) ?>
                Guests
            </strong>

        </div>


        <div class="bw-review-row">

            <span>Type</span>

            <strong>

                <?php if ($booking_result['booking_type'] === 'Day Use'): ?>

                    Day Use

                <?php elseif ($booking_result['booking_type'] === 'Exclusive'): ?>

                    Exclusive Resort

                <?php else: ?>

                    Overnight (<?= (int)$booking_result['nights'] ?> night(s))

                <?php endif; ?>

            </strong>

        </div>


        <div class="bw-review-row">

            <span>Payment</span>

            <strong>
                <?= $booking_result['payment_method'] ?>
            </strong>

        </div>

        <?php if (!empty($booking_result['balance']) && $booking_result['balance'] > 0): ?>
        <div class="bw-review-row">
            <span>Down payment sent</span>
            <strong>₱<?= number_format($booking_result['amount_paid'], 2) ?></strong>
        </div>
        <div class="bw-review-row">
            <span>Balance due by <?= date('M j, Y', strtotime($booking_result['balance_due'])) ?></span>
            <strong>₱<?= number_format($booking_result['balance'], 2) ?></strong>
        </div>
        <?php endif; ?>


        <div class="bw-review-total">

            <span>
                Total
            </span>

            <strong>
                ₱<?= number_format(
                    $booking_result['total'],
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <p style="font-size:0.8rem;color:red;margin-bottom:1.5rem;">

        We will send your booking confirmation to your email
        once your reservation is confirmed.

    </p>


    <div class="bw-success-actions">

        <a href="check_booking.php?code=<?= $booking_result['code'] ?>"
           class="bw-btn bw-btn-primary">

            Track Booking

        </a>


        <a href="receipt.php?code=<?= $booking_result['code'] ?>"
           target="_blank"
           class="bw-btn bw-btn-ghost">

            🧾 View Receipt

        </a>


        <a href="index.php"
           class="bw-btn bw-btn-ghost">

            Back to Home

        </a>

    </div>

</div>


<?php else: ?>


<div class="bw-header">

    <h1>
        Reserve Your
        <em>Cottage</em>
    </h1>

    <p>
        Follow the steps below and we'll confirm your stay shortly.
    </p>

</div>


<div class="bw-steps"
     id="bwSteps">

    <div class="bw-step is-active"
         data-step="1">

        <div class="bw-step-num">
            1
        </div>

        <div class="bw-step-text">

            <span class="bw-step-title">
                Your Stay
            </span>

            <span class="bw-step-sub">
                Choose dates &amp; guests
            </span>

        </div>

    </div>


    <div class="bw-step-connector"></div>


    <div class="bw-step"
         data-step="2">

        <div class="bw-step-num">
            2
        </div>

        <div class="bw-step-text">

            <span class="bw-step-title">
                Accommodation
            </span>

            <span class="bw-step-sub">
                Choose your cottage
            </span>

        </div>

    </div>


    <div class="bw-step-connector"></div>


    <div class="bw-step"
         data-step="3">

        <div class="bw-step-num">
            3
        </div>

        <div class="bw-step-text">

            <span class="bw-step-title">
                Details
            </span>

            <span class="bw-step-sub">
                Guest information
            </span>

        </div>

    </div>


    <div class="bw-step-connector"></div>


    <div class="bw-step"
         data-step="4">

        <div class="bw-step-num">
            4
        </div>

        <div class="bw-step-text">

            <span class="bw-step-title">
                Confirm
            </span>

            <span class="bw-step-sub">
                Review &amp; confirm
            </span>

        </div>

    </div>

</div>


<?php if (!empty($errors)): ?>

<div class="bw-alert-error">

    <strong>
        Please fix the following:
    </strong>

    <ul>

        <?php foreach ($errors as $e): ?>

            <li>
                <?= htmlspecialchars($e) ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<form action="booking.php"
      method="POST"
      enctype="multipart/form-data"
      id="bookingForm">


<div class="bw-layout">


<div class="bw-form-col">


<div class="bw-panel bw-step-panel is-current"
     data-panel="1">

    <h2>
        When are you staying?
    </h2>


    <div class="bw-field">

        <label>
            Booking Type *
        </label>


        <div class="bw-segmented"
             id="bookingTypeOptions">


            <label class="bw-segmented-btn
                <?= $booking_type_val === 'Day Use'
                    ? 'is-selected'
                    : '' ?>"
                data-booking-type="Day Use">

                <input type="radio"
                       name="booking_type"
                       value="Day Use"
                       <?= $booking_type_val === 'Day Use'
                           ? 'checked'
                           : '' ?>>

                <span>
                    ☀️ Day Use
                </span>

            </label>


            <label class="bw-segmented-btn
                <?= $booking_type_val === 'Overnight'
                    ? 'is-selected'
                    : '' ?>"
                data-booking-type="Overnight">

                <input type="radio"
                       name="booking_type"
                       value="Overnight"
                       <?= $booking_type_val === 'Overnight'
                           ? 'checked'
                           : '' ?>>

                <span>
                    🌙 Overnight
                </span>

            </label>


            <label class="bw-segmented-btn
                <?= $booking_type_val === 'Exclusive'
                    ? 'is-selected'
                    : '' ?>"
                data-booking-type="Exclusive">

                <input type="radio"
                       name="booking_type"
                       value="Exclusive"
                       <?= $booking_type_val === 'Exclusive'
                           ? 'checked'
                           : '' ?>>

                <span>
                    🏝️ Exclusive Resort
                </span>

            </label>

        </div>


        <p class="bw-segmented-hint"
           id="bookingTypeHint">

            Overnight stays are available for Rooms only.

        </p>

    </div>


    <div class="bw-note-box"
         id="exclusiveNotice"
         style="<?= $booking_type_val === 'Exclusive'
             ? ''
             : 'display:none;' ?>">

        <span>
            🏝️
        </span>

        <div>

            <strong>
                Exclusive Resort Booking
            </strong>

            The entire resort will be reserved exclusively
            for your event.

            All cottages and resort facilities will be unavailable
            to other guests during your booking.

            Maximum capacity:
            <strong>200 guests</strong>.

        </div>

    </div>


    <div class="bw-field-row">


        <div class="bw-field">

            <label id="checkInLabel">

                <?= $booking_type_val === 'Overnight'
                    ? 'Check-in Date *'
                    : ($booking_type_val === 'Exclusive'
                        ? 'Event Start Date *'
                        : 'Visit Date *') ?>

            </label>


            <input type="date"
                   name="check_in"
                   id="check_in"
                   value="<?= htmlspecialchars($check_in_val) ?>"
                   min="<?= date('Y-m-d') ?>"
                   required>

        </div>


        <div class="bw-field"
             id="checkOutField"
             style="<?= in_array(
                 $booking_type_val,
                 ['Day Use', 'Exclusive'],
                 true
             ) ? 'display:none;' : '' ?>">

            <label>
                Check-out Date *
            </label>


            <input type="date"
                   name="check_out"
                   id="check_out"
                   value="<?= htmlspecialchars($check_out_val) ?>"
                   min="<?= date(
                       'Y-m-d',
                       strtotime('+1 day')
                   ) ?>"
                   <?= $booking_type_val === 'Overnight'
                       ? 'required'
                       : '' ?>>

        </div>

    </div>


    <p class="date-unavailable-note"
       id="unavailableDatesNote">
    </p>


    <div class="bw-field">

        <label>
            Number of Guests *
        </label>


        <div class="bw-guest-stepper">

            <div class="bw-guest-label">

                👤

                <span id="guestCountLabel">

                    <?= $guests ?>
                    <?= $guests == 1
                        ? 'Guest'
                        : 'Guests' ?>

                </span>

            </div>


            <div class="bw-guest-btns">

                <button type="button"
                        class="bw-guest-btn"
                        id="guestMinus">

                    −

                </button>


                <button type="button"
                        class="bw-guest-btn"
                        id="guestPlus">

                    +

                </button>

            </div>

        </div>


        <input type="number"
               name="num_guests"
               id="num_guests"
               value="<?= max(0, min(200, (int)$guests)) ?>"
               min="1"
               max="200"
               step="1"
               inputmode="numeric"
               aria-label="Number of Guests"
               oninput="this.value = Math.max(0, Math.min(200, parseInt(this.value || 0, 10))); setGuestCount(parseInt(this.value, 10));"
               style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;margin-top:8px;">

    </div>


    <div class="bw-note-box">

        <span>
            ✅
        </span>

        <div>

            <strong>
                Free cancellation
            </strong>

            Cancel for free up to 24 hours
            before check-in.

        </div>

    </div>


    <div class="bw-actions">

        <button type="button"
                class="bw-btn bw-btn-primary"
                id="toStep2">

            Continue to Accommodation →

        </button>

    </div>

</div>


<div class="bw-panel bw-step-panel"
     data-panel="2">

    <h2>
        Choose Your Accommodation
    </h2>


    <p style="font-size:0.85rem;color:var(--text-light);margin:-0.9rem 0 1.1rem;">

        Select a cottage, kubo, or pavilion for your stay.

    </p>


    <p id="availabilityStatus"
       style="font-size:0.8rem;color:var(--text-light);margin:-0.6rem 0 1rem;">

    </p>


    <div class="bw-note-box"
         id="exclusiveAccommodationNotice"
         style="<?= $booking_type_val === 'Exclusive'
             ? ''
             : 'display:none;' ?>">

        <span>
            🏝️
        </span>

        <div>

            <strong>
                Entire Resort Reserved
            </strong>

            You do not need to select an individual cottage.
            Your Exclusive Resort booking covers the entire resort.

        </div>

    </div>


    <div class="bw-note-box"
         id="exclusivePaymentNotice"
         style="<?= $booking_type_val === 'Exclusive'
             ? ''
             : 'display:none;' ?>">

        <span>
            💳
        </span>

        <div>
            <strong>
                Exclusive Resort Payment Policy
            </strong>

           <strong> Exclusive Resort rental is paid through GCash only.
            Choose to pay the full ₱40,000.00, or a 20% down payment of
            ₱8,000.00 to secure the reservation. The remaining 80% balance
            (₱32,000.00) must be paid through GCash on or before the event
            date, using <em>My Booking</em> with your booking code.</strong>
        </div>

    </div>


    <div class="bw-filter-tabs"
         id="bwFilterTabs">

        <div class="bw-filter-tab is-active"
             data-filter="all">

            All

        </div>


        <?php foreach (
            array_keys($grouped_cottages)
            as $cat
        ): ?>

            <div class="bw-filter-tab"
                 data-filter="<?= htmlspecialchars($cat) ?>">

                <?= htmlspecialchars($cat) ?>

            </div>

        <?php endforeach; ?>

    </div>


    <div id="cottageOptions">

        <?php

        $event_options = [

            'Party Event' => [
                'icon' => '🎉',
                'desc' => 'General parties, reunions, and get-togethers.'
            ],

            'Birthday Event' => [
                'icon' => '🎂',
                'desc' => 'Debut Birtday Celebration.'
            ],

            'Marriage Event' => [
                'icon' => '💍',
                'desc' => 'Weddings and wedding-related celebrations.'
            ],

        ];

        ?>


        <?php foreach (
            $grouped_cottages as $cat => $cats_cottages
        ): ?>


            <div class="bw-cat-heading"
                 data-cat-heading="<?= htmlspecialchars($cat) ?>">

                <?= $cat_icons[$cat] ?? '🏠' ?>

                <?= htmlspecialchars($cat) ?>

            </div>


            <div class="bw-cottage-grid"
                 data-cat-group="<?= htmlspecialchars($cat) ?>">


                <?php foreach (
                    $cats_cottages as $c
                ):

                    $isSelected =
                        ($c['id'] == $cottage_id);

                    $isBooked =
                        (bool)$c['is_booked'];

                    $thumb =
                        $cottage_thumbs[$c['id']]
                        ?? '';

                    $isPavilionCard =
                        ($cat === 'Pavilion');

                ?>


                <?php if ($isPavilionCard): ?>

                    <div class="bw-cottage-card-wrap
                        <?= $isSelected
                            ? 'is-selected'
                            : '' ?>">

                <?php endif; ?>


                <label class="bw-cottage-card
                    <?= $isSelected
                        ? 'is-selected'
                        : '' ?>
                    <?= $isBooked
                        ? 'is-booked'
                        : '' ?>"

                    data-price="<?= $c['price_per_night'] ?>"
                    data-name="<?= htmlspecialchars($c['name']) ?>"
                    data-capacity="<?= $c['capacity'] ?>"
                    data-category="<?= htmlspecialchars($cat) ?>"
                    data-thumb="<?= htmlspecialchars($thumb) ?>">


                    <input type="radio"
                           name="cottage_id"
                           value="<?= $c['id'] ?>"
                           <?= $isSelected
                               ? 'checked'
                               : '' ?>
                           <?= $isBooked
                               ? 'disabled'
                               : '' ?>>


                    <div class="bw-cc-img">

                        <?php if ($thumb): ?>

                            <img src="<?= htmlspecialchars($thumb) ?>"
                                 alt="<?= htmlspecialchars($c['name']) ?>">

                            <span class="bw-cc-zoom-hint">
                                🔍 Enlarge
                            </span>

                        <?php else: ?>

                            <?= $cat_icons[$cat] ?? '🏠' ?>

                        <?php endif; ?>


                        <span class="bw-cc-badge
                            <?= $isBooked
                                ? 'is-booked'
                                : '' ?>">

                            <?= $isBooked
                                ? 'Occupied'
                                : 'Available' ?>

                        </span>

                    </div>


                    <div class="bw-cc-body">

                        <strong>
                            <?= htmlspecialchars($c['name']) ?>
                        </strong>


                        <span class="bw-cc-cap">

                            👥 Up to
                            <?= $c['capacity'] ?>
                            guests

                        </span>


                        <div class="bw-cc-foot">

                            <?php if ($cat === 'Pavilion'): ?>

                                <div class="bw-cc-price bw-cc-price-event">
                                    Priced per occasion
                                </div>

                            <?php elseif ($cat === OVERNIGHT_CATEGORY): ?>

                                <div class="bw-cc-price">

                                    ₱<?= number_format(
                                        $c['price_per_night'],
                                        0
                                    ) ?>

                                    <small>
                                        / day or night
                                    </small>

                                </div>

                            <?php else: ?>

                                <div class="bw-cc-price">

                                    ₱<?= number_format(
                                        $c['price_per_night'],
                                        0
                                    ) ?>

                                    <small>
                                        / day
                                    </small>

                                </div>

                            <?php endif; ?>


                            <span class="bw-cc-select">

                                <?= $isBooked
                                    ? 'Unavailable'
                                    : 'Select' ?>

                            </span>

                        </div>

                    </div>

                </label>


                <?php if ($isPavilionCard): ?>


                    <div class="bw-pavilion-accordion
                        <?= $isSelected
                            ? 'is-expanded'
                            : '' ?>"

                        data-pavilion-accordion="<?= $c['id'] ?>">


                        <div class="bw-pavilion-accordion-inner">

                            <p class="bw-pavilion-accordion-label">

                                What's the occasion?

                                <span>
                                    The Pavilion is booked per event.
                                </span>

                            </p>


                            <div class="bw-option-list bw-option-list-compact"
                                 data-pavilion-events="<?= $c['id'] ?>">


                                <?php foreach (
                                    $event_options as $etype => $einfo
                                ):

                                    $isEtSelected =
                                        $isSelected &&
                                        (
                                            ($_POST['event_type'] ?? '')
                                            === $etype
                                        );

                                ?>


                                    <label class="bw-option-card
                                        <?= $isEtSelected
                                            ? 'is-selected'
                                            : '' ?>"

                                        data-event-type="<?= htmlspecialchars($etype) ?>">


                                        <input type="radio"
                                               name="event_type"
                                               value="<?= $etype ?>"
                                               <?= $isEtSelected
                                                   ? 'checked'
                                                   : '' ?>>


                                        <div class="bw-option-icon">
                                            <?= $einfo['icon'] ?>
                                        </div>


                                        <div class="bw-option-text">

                                            <strong>
                                                <?= $etype ?>
                                            </strong>

                                            <span>
                                                <?= $einfo['desc'] ?>
                                            </span>

                                        </div>


                                        <div class="bw-option-price"
                                             id="etp-<?= $c['id'] ?>-<?= htmlspecialchars(str_replace(' ', '', $etype)) ?>">

                                            —

                                        </div>


                                    </label>


                                <?php endforeach; ?>


                            </div>

                        </div>

                    </div>


                    </div>

                <?php endif; ?>


                <?php endforeach; ?>

            </div>

        <?php endforeach; ?>

    </div>


    <div class="bw-nav-row">

        <button type="button"
                class="bw-btn bw-btn-ghost"
                data-back="1">

            ← Back

        </button>


        <button type="button"
                class="bw-btn bw-btn-primary"
                id="toStep3">

            Continue to Details →

        </button>

    </div>

</div>


<div class="bw-panel bw-step-panel"
     data-panel="3">

    <h2>
        Guest Information
    </h2>


    <div class="bw-field">

        <label>
            Full Name *
        </label>

        <input type="text"
               name="guest_name"
               placeholder="Enter your name...."
               value="<?= htmlspecialchars(
                   $_POST['guest_name'] ?? ''
               ) ?>"
               required>

    </div>


    <div class="bw-field-row">


        <div class="bw-field">

            <label>
                Email Address *
            </label>

            <input type="email"
                   name="guest_email"
                   placeholder="Enter your gmail...."
                   value="<?= htmlspecialchars(
                       $_POST['guest_email'] ?? ''
                   ) ?>"
                   pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$"
                   title="Please enter a valid Gmail address (e.g. yourname@gmail.com)"
                   required>

            <span class="bw-field-hint">
                Must be a valid @gmail.com address
            </span>

        </div>


        <div class="bw-field">

            <label>
                Phone Number *
            </label>

            <input type="tel"
                   name="guest_phone"
                   placeholder="09XXXXXXXXX"
                   value="<?= htmlspecialchars(
                       $_POST['guest_phone'] ?? ''
                   ) ?>"
                   inputmode="numeric"
                   pattern="[0-9]{11}"
                   minlength="11"
                   maxlength="11"
                   title="Enter an 11-digit phone number (e.g. 09171234567)"
                   required>

            <span class="bw-field-hint">
                11 digits, e.g. 09171234567
            </span>

        </div>

    </div>


    <div class="bw-field">

        <label>
            Special Request
            <span class="optional">
                (optional)
            </span>
        </label>

        <textarea name="special_requests"
                  rows="3"
                  placeholder="e.g. Near the adults pool, Near the kids pool, etc."><?= htmlspecialchars(
                      $_POST['special_requests'] ?? ''
                  ) ?></textarea>

    </div>


    <div class="bw-nav-row">

        <button type="button"
                class="bw-btn bw-btn-ghost"
                data-back="2">

            ← Back

        </button>


        <button type="button"
                class="bw-btn bw-btn-gold"
                id="toStep4">

            Review Booking →

        </button>

    </div>

</div>


<div class="bw-panel bw-step-panel"
     data-panel="4">

    <h2>
        Review &amp; Confirm
    </h2>


    <div class="bw-review-grid"
         id="reviewGrid">

        <div>

            <div class="bw-review-row">

                <span>
                    Accommodation
                </span>

                <strong id="rev-cottage">
                    —
                </strong>

            </div>


            <div class="bw-review-row">

                <span id="rev-checkin-label">
                    Check-in
                </span>

                <strong id="rev-checkin">
                    —
                </strong>

            </div>


            <div class="bw-review-row"
                 id="rev-checkout-row">

                <span>
                    Check-out
                </span>

                <strong id="rev-checkout">
                    —
                </strong>

            </div>

        </div>


        <div>

            <div class="bw-review-row">

                <span>
                    Guests
                </span>

                <strong id="rev-guests">
                    —
                </strong>

            </div>


            <div class="bw-review-row">

                <span id="rev-nights-label">
                    Nights
                </span>

                <strong id="rev-nights">
                    —
                </strong>

            </div>


            <div class="bw-review-row">

                <span>
                    Guest Name
                </span>

                <strong id="rev-name">
                    —
                </strong>

            </div>

        </div>

    </div>


    <div class="bw-review-total">

        <span>
            Total
        </span>

        <strong id="rev-total">
            ₱0.00
        </strong>

    </div>


    <h2 style="margin-top:1.75rem;">
        Payment Method
    </h2>


    <div class="bw-option-list">


        <label class="bw-option-card
            <?= ($_POST['payment_method'] ?? 'Pay on Arrival') === 'Pay on Arrival'
                ? 'is-selected'
                : '' ?>"
            id="opt-arrival">

            <input type="radio"
                   name="payment_method"
                   value="Pay on Arrival"
                   <?= ($_POST['payment_method'] ?? 'Pay on Arrival') === 'Pay on Arrival'
                       ? 'checked'
                       : '' ?>>

            <div class="bw-option-icon">
                🏨
            </div>

            <div class="bw-option-text">

                <strong>
                    Pay on Arrival
                </strong>

                <span>
                    No online payment needed —
                    settle in cash or GCash when
                    you check in.
                </span>

            </div>

        </label>


        <label class="bw-option-card
            <?= ($_POST['payment_method'] ?? '') === 'GCash'
                ? 'is-selected'
                : '' ?>"
            id="opt-gcash">

            <input type="radio"
                   name="payment_method"
                   value="GCash"
                   <?= ($_POST['payment_method'] ?? '') === 'GCash'
                       ? 'checked'
                       : '' ?>>

            <div class="bw-option-icon">
                💵
            </div>

            <div class="bw-option-text">

                <strong>
                    Pay via GCash (QRcode)
                </strong>

                <span>
                    Send payment yourself via the
                    GCash app and upload a screenshot
                    as proof.
                </span>

            </div>

        </label>

    </div>


    <div class="bw-gcash-box"
         id="exclusivePayOption"
         style="<?= $booking_type_val === 'Exclusive' ? '' : 'display:none;' ?>">

        <strong style="color:var(--green-deep);">
            💳 How much would you like to pay now?
        </strong>

        <?php $sel_pay_option = ($_POST['payment_option'] ?? 'Downpayment') === 'Full' ? 'Full' : 'Downpayment'; ?>

        <label style="display:block;margin-top:0.75rem;cursor:pointer;">
            <input type="radio" name="payment_option" value="Downpayment"
                   <?= $sel_pay_option === 'Downpayment' ? 'checked' : '' ?>>
            <strong>20% down payment — ₱8,000.00</strong><br>
            <small>Balance of ₱32,000.00 is paid via GCash on or before the event date.</small>
        </label>

        <label style="display:block;margin-top:0.75rem;cursor:pointer;">
            <input type="radio" name="payment_option" value="Full"
                   <?= $sel_pay_option === 'Full' ? 'checked' : '' ?>>
            <strong>Pay in full — ₱40,000.00</strong><br>
            <small>Nothing left to pay later.</small>
        </label>

    </div>


    <div class="bw-gcash-box"
         id="arrivalForm"
         style="display:none;">

        <strong style="color:var(--green-deep);">
            🏨 How it works
        </strong>

        <ol>

            <li>
                Click
                <strong>
                    "Confirm Reservation"
                </strong>
                below
            </li>

            <li>
                Your booking is saved as
                <strong>
                    Pending
                </strong>
            </li>

            <li>
                Settle the full amount in cash
                or GCash when you arrive at
                the resort
            </li>

        </ol>

    </div>


    <div class="bw-gcash-box"
         id="gcashForm"
         style="display:none;">

        <strong style="color:var(--green-deep);">

            💵 Pay first, then submit this form

        </strong>


        <ol>

            <li>
                Open your GCash app and scan
                the QR code below
            </li>

        </ol>


        <div class="bw-gcash-qr">

            <?php if ($gcash_qr_url): ?>

                <img src="<?= htmlspecialchars($gcash_qr_url) ?>"
                     alt="GCash QR Code">

            <?php else: ?>

                <div style="font-size:0.8rem;color:#888;">

                    QR code coming soon —
                    please contact the resort
                    for GCash payment details.

                </div>

            <?php endif; ?>


            <div style="font-size:0.72rem;color:#5a8a6f;font-weight:700;text-transform:uppercase;margin-top:0.5rem;">

                GCash Account

            </div>


            <div style="font-size:1rem;font-weight:700;color:var(--green-deep);">

                <?= htmlspecialchars(
                    $gcash_settings['account_name']
                ) ?>

            </div>

        </div>


        <ol start="2">

            <li>

                Enter
                <strong id="gcashAmountHint">
                    the total amount
                </strong>
                in the GCash app and complete
                the payment

            </li>


            <li>
                Take a
                <strong>
                    screenshot
                </strong>
                of your GCash payment receipt
            </li>


            <li>
                Fill in the reference number
                and upload the screenshot below
            </li>


            <li>
                Submit — your booking will show as
                <strong>
                    Pending Verification
                </strong>
                until our team confirms it
            </li>

        </ol>


        <div class="bw-field"
             style="margin-top:0.9rem;">

            <label>
                GCash Reference Number *
            </label>

            <input type="text"
                   name="gcash_reference"
                   id="gcash_reference"
                   placeholder="e.g. 1234567890123"
                   value="<?= htmlspecialchars(
                       $_POST['gcash_reference'] ?? ''
                   ) ?>">

        </div>


        <div class="bw-field-row">


            <div class="bw-field">

                <label>
                    Name Used to Send Payment *
                </label>

                <input type="text"
                       name="gcash_sender_name"
                       id="gcash_sender_name"
                       placeholder="Same as GCash account name"
                       value="<?= htmlspecialchars(
                           $_POST['gcash_sender_name'] ?? ''
                       ) ?>">

            </div>


            <div class="bw-field">

                <label>
                    Sender GCash Number
                    <span class="optional">
                        (optional)
                    </span>
                </label>

                <input type="tel"
                       name="gcash_sender_phone"
                       id="gcash_sender_phone"
                       placeholder="09XX XXX XXXX"
                       value="<?= htmlspecialchars(
                           $_POST['gcash_sender_phone'] ?? ''
                       ) ?>">

            </div>

        </div>


        <div class="bw-field">

            <label>
                Upload Screenshot of Payment *
            </label>

            <input type="file"
                   name="gcash_proof"
                   id="gcash_proof"
                   accept="image/png,image/jpeg,image/gif,image/webp">


            <img id="gcashProofPreview"
                 src=""
                 alt=""
                 style="display:none;max-width:200px;margin-top:0.6rem;border-radius:8px;border:1px solid #e5e8e5;">

        </div>


        <p style="font-size:0.78rem;color:var(--text-light);">

            🔒 Your screenshot is only used to verify this payment —
            please make sure the amount, date, and reference number
            are visible.

        </p>

    </div>


    <div class="bw-nav-row">

        <button type="button"
                class="bw-btn bw-btn-ghost"
                data-back="3">

            ← Back

        </button>


        <button type="submit"
                class="bw-btn bw-btn-gold"
                id="submitBtn">

            Confirm Reservation 💵

        </button>

    </div>


    <p class="form-note"
       id="formNote"
       style="font-size:0.78rem;color:var(--text-light);margin-top:0.6rem;text-align:right;">

        No payment required online.
        Settle upon arrival.

    </p>

</div>


</div>


<div class="bw-sidebar">

    <div class="bw-summary-card">

        <h3>
            Your Booking
        </h3>


        <div id="sidebarEmpty"
             class="bw-summary-empty">

            <span class="bw-summary-icon">
                📅
            </span>

            <p>
                No accommodation
                <br>
                selected yet
            </p>

        </div>


        <div id="sidebarFilled"
             style="display:none;">

            <div class="bw-summary-cottage"
                 id="sidebarCottageBox">

                <div class="bw-summary-cottage-img"
                     id="sidebarCottageImg">

                    🏡

                </div>


                <div class="bw-summary-cottage-text">

                    <strong id="summary-cottage-name">
                        —
                    </strong>

                    <span id="summary-cottage-cat">
                        —
                    </span>

                </div>

            </div>

        </div>


        <div class="bw-summary-row">

            <span id="summary-checkin-label">

                <?= $booking_type_val === 'Day Use'
                    ? 'Visit Date'
                    : ($booking_type_val === 'Exclusive'
                        ? 'Event Start'
                        : 'Check-in') ?>

            </span>


            <strong id="summary-checkin">

                <?= $check_in
                    ? date(
                        'M d, Y',
                        strtotime($check_in)
                    )
                    : '—' ?>

            </strong>

        </div>


        <div class="bw-summary-row"
             id="summary-checkout-row"
             style="<?= in_array(
                 $booking_type_val,
                 ['Day Use', 'Exclusive'],
                 true
             ) ? 'display:none;' : '' ?>">

            <span>
                Check-out
            </span>

            <strong id="summary-checkout">

                <?= $check_out
                    ? date(
                        'M d, Y',
                        strtotime($check_out)
                    )
                    : '—' ?>

            </strong>

        </div>


        <div class="bw-summary-row">

            <span>
                Guests
            </span>

            <strong id="summary-guests">

                <?= $guests ?>
                Guests

            </strong>

        </div>


        <div class="bw-summary-divider"></div>


        <div class="bw-summary-total">

            <span>
                Total
            </span>

            <strong id="summary-total">

                <?= $total_price
                    ? '₱' . number_format(
                        $total_price,
                        2
                    )
                    : '₱0.00' ?>

            </strong>

        </div>


        <div class="bw-summary-note">

            <span>
                ✅
            </span>

            <div>

                <strong style="color:var(--white);">
                    Free cancellation
                </strong>

                Cancel for free up to 24 hours
                before check-in.

            </div>

        </div>

    </div>

</div>


</div>

</form>


<div class="lightbox"
     id="cottageLightbox">

    <button type="button"
            class="lightbox-close"
            id="cottageLightboxClose"
            aria-label="Close">

        ✕

    </button>


    <img src=""
         alt=""
         class="lightbox-img"
         id="cottageLightboxImg">


    <div class="lightbox-caption"
         id="cottageLightboxCaption">
    </div>

</div>


<?php endif; ?>


</div>

</div>


<footer class="footer">

    <div class="footer-bottom">

        <p>
            &copy; <?= date('Y') ?>
            S-Five Inland Resort.
        </p>

    </div>

</footer>


<div class="s5-contact-footer-card">

    <img src="images/sfive_logo.png"
         alt="S-Five Inland Resort"
         class="nav-logo-img">

    <h3>
        S-Five Inland Resort
    </h3>

    <p class="tagline">
        Relax. Refresh. Reconnect.
    </p>

    <p class="s5-copyright">

        &copy; <?= date('Y') ?>
        S-Five Inland Resort.
        All rights reserved.

    </p>

</div>


<script>

const cottageData = {

<?php foreach ($all_cottages as $c): ?>

    <?= $c['id'] ?>: {

        name:
            "<?= addslashes($c['name']) ?>",

        price:
            <?= $c['price_per_night'] ?>,

        capacity:
            <?= $c['capacity'] ?>,

        booked:
            <?= (int)$c['is_booked'] > 0
                ? 'true'
                : 'false' ?>

    },

<?php endforeach; ?>

};


const pavilionEventPrices =
    <?= json_encode($pavilion_event_prices) ?>;


let selectedCottageId =
    <?= $cottage_id ?: 0 ?>;


let selectedPrice =
    <?= $booking_type_val === 'Exclusive'
        ? '40000'
        : ($selected_cottage ? $selected_unit_price : '0') ?>;


let selectedCapacity =
    <?= $selected_cottage
        ? $selected_cottage['capacity']
        : 0 ?>;


let selectedCottageName =
    <?= $selected_cottage
        ? json_encode($selected_cottage['name'])
        : "''" ?>;


let selectedCategory =
    <?= $selected_cottage
        ? json_encode($selected_cottage['category'])
        : "''" ?>;


const OVERNIGHT_CATEGORY =
    <?= json_encode(OVERNIGHT_CATEGORY) ?>;


let bookingType =
    <?= json_encode($booking_type_val) ?>;


let currentStep = 1;


function getUnitPrice(
    cottageId,
    eventType
) {

    const base =
        cottageData[cottageId]
            ? cottageData[cottageId].price
            : 0;


    if (
        eventType &&
        pavilionEventPrices[cottageId] &&
        pavilionEventPrices[cottageId][eventType]
            !== undefined
    ) {

        return pavilionEventPrices[cottageId][eventType];
    }


    return base;
}


function populateEventPrices(
    cottageId
) {

    document.querySelectorAll(
        '[data-pavilion-events="' +
        cottageId +
        '"] .bw-option-card'
    ).forEach(card => {

        const etype =
            card.dataset.eventType;

        const priceEl =
            card.querySelector(
                '.bw-option-price'
            );

        if (!priceEl) {
            return;
        }


        const price =
            getUnitPrice(
                cottageId,
                etype
            );


        priceEl.textContent =
            price
                ? '₱' +
                  price.toLocaleString(
                      'en-PH',
                      {
                          minimumFractionDigits: 0
                      }
                  ) +
                  '/day'
                : '—';

    });
}


<?php if (!$success): ?>


function goToStep(n) {

    document.querySelectorAll(
        '.bw-step-panel'
    ).forEach(p => {

        p.classList.toggle(
            'is-current',
            p.dataset.panel == n
        );

    });


    document.querySelectorAll(
        '.bw-step'
    ).forEach(s => {

        const step =
            parseInt(s.dataset.step);


        s.classList.toggle(
            'is-active',
            step === n
        );


        s.classList.toggle(
            'is-done',
            step < n
        );

    });


    currentStep = n;


    if (n === 4) {
        updateReview();
    }


    window.scrollTo({
        top:
            document.getElementById(
                'bwSteps'
            ).offsetTop - 90,

        behavior: 'smooth'
    });
}


document.querySelectorAll(
    '[data-back]'
).forEach(btn => {

    btn.addEventListener(
        'click',
        () => goToStep(
            parseInt(btn.dataset.back)
        )
    );

});


document.getElementById(
    'toStep2'
).addEventListener(
    'click',
    () => {

        if (
            !document.getElementById(
                'check_in'
            ).value
        ) {

            alert(
                'Please choose your date.'
            );

            return;
        }


        // Only Overnight requires the guest to enter a checkout date.
        // Day Use and Exclusive use an internal next-day checkout.
        if (
            bookingType === 'Overnight' &&
            !document.getElementById(
                'check_out'
            ).value
        ) {

            alert(
                'Please choose your check-out date.'
            );

            return;
        }


        goToStep(2);
    }
);


document.getElementById(
    'toStep3'
).addEventListener(
    'click',
    () => {

        if (
            bookingType !== 'Exclusive' &&
            !selectedCottageId
        ) {

            alert(
                'Please select an accommodation.'
            );

            return;
        }


        if (
            selectedCategory === 'Pavilion' &&
            !document.querySelector(
                'input[name=event_type]:checked'
            )
        ) {

            alert(
                "Please select the occasion for your Pavilion booking."
            );

            return;
        }


        goToStep(3);
    }
);


const GMAIL_RE =
    /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;


const PHONE_RE =
    /^[0-9]{11}$/;


document.getElementById(
    'toStep4'
).addEventListener(
    'click',
    () => {

        const name =
            document.querySelector(
                'input[name=guest_name]'
            ).value.trim();


        const email =
            document.querySelector(
                'input[name=guest_email]'
            ).value.trim();


        const phone =
            document.querySelector(
                'input[name=guest_phone]'
            ).value.trim();


        if (!name || !email || !phone) {

            alert(
                'Please fill in your name, email, and phone number.'
            );

            return;
        }


        if (!GMAIL_RE.test(email)) {

            alert(
                'Please enter a valid Gmail address (e.g. yourname@gmail.com).'
            );

            return;
        }


        if (!PHONE_RE.test(phone)) {

            alert(
                'Please enter a valid 11-digit phone number (e.g. 09171234567).'
            );

            return;
        }


        goToStep(4);
    }
);


document.querySelector(
    'input[name=guest_phone]'
).addEventListener(
    'input',
    (e) => {

        e.target.value =
            e.target.value
                .replace(/[^0-9]/g, '')
                .slice(0, 11);

    }
);


function syncDayUseCheckout() {

    // Day Use and Exclusive Resort both use one visible date.
    // Keep an internal next-day checkout for availability checks.
    if (
        bookingType !== 'Day Use' &&
        bookingType !== 'Exclusive'
    ) {
        return;
    }


    const ci =
        document.getElementById(
            'check_in'
        ).value;


    if (!ci) {
        return;
    }


    const d =
        new Date(ci);


    d.setDate(
        d.getDate() + 1
    );


    document.getElementById(
        'check_out'
    ).value =
        d.toISOString()
         .split('T')[0];
}


function applyBookingTypeUI() {

    const isOvernight =
        bookingType === 'Overnight';


    const isExclusive =
        bookingType === 'Exclusive';


    const exclusiveNotice =
        document.getElementById(
            'exclusiveNotice'
        );


    const exclusiveAccommodationNotice =
        document.getElementById(
            'exclusiveAccommodationNotice'
        );


    if (exclusiveNotice) {

        exclusiveNotice.style.display =
            isExclusive
                ? 'block'
                : 'none';
    }


    if (exclusiveAccommodationNotice) {

        exclusiveAccommodationNotice.style.display =
            isExclusive
                ? 'block'
                : 'none';
    }


    const exclusivePaymentNotice =
        document.getElementById(
            'exclusivePaymentNotice'
        );

    if (exclusivePaymentNotice) {

        exclusivePaymentNotice.style.display =
            isExclusive
                ? 'block'
                : 'none';
    }


    // Exclusive Resort: GCash only, with a Full / Down payment choice.
    const exclusivePayOption =
        document.getElementById('exclusivePayOption');

    if (exclusivePayOption) {
        exclusivePayOption.style.display =
            isExclusive ? 'block' : 'none';
    }

    const optArrival =
        document.getElementById('opt-arrival');

    if (optArrival) {
        optArrival.style.display =
            isExclusive ? 'none' : '';
    }

    if (isExclusive) {
        const gcashRadio = document.querySelector(
            'input[name=payment_method][value="GCash"]'
        );

        if (gcashRadio && !gcashRadio.checked) {
            gcashRadio.checked = true;

            document.querySelectorAll(
                '#opt-arrival, #opt-gcash'
            ).forEach(o => o.classList.remove('is-selected'));

            document.getElementById('opt-gcash')
                .classList.add('is-selected');

            applyPaymentMethod('GCash');
        }
    }

    updateExclusiveAmountHint();


    document.getElementById(
        'checkOutField'
    ).style.display =
        isOvernight
            ? ''
            : 'none';


    document.getElementById(
        'check_out'
    ).required =
        isOvernight;


    document.getElementById(
        'checkInLabel'
    ).textContent =
        isOvernight
            ? 'Check-in Date *'
            : (
                isExclusive
                    ? 'Event Start Date *'
                    : 'Visit Date *'
            );


    if (
        bookingType === 'Day Use' ||
        isExclusive
    ) {
        syncDayUseCheckout();
    }


    if (isExclusive) {

        selectedCottageId = 0;

        selectedPrice = 40000;

        selectedCapacity = 200;

        selectedCottageName =
            'Exclusive Resort';

        selectedCategory =
            'Exclusive';


        document.querySelectorAll(
            '.bw-cottage-card'
        ).forEach(card => {

            card.style.display =
                'none';

        });


        document.querySelectorAll(
            '.bw-cat-heading'
        ).forEach(el => {

            el.style.display =
                'none';

        });


        document.querySelectorAll(
            '.bw-cottage-grid'
        ).forEach(el => {

            el.style.display =
                'none';

        });


        document.querySelectorAll(
            '.bw-filter-tab'
        ).forEach(el => {

            el.style.display =
                'none';

        });


        const guestSelect =
            document.getElementById(
                'num_guests'
            );


        if (
            parseInt(
                guestSelect.value
            ) > 200
        ) {

            guestSelect.value = 200;
        }


        updateSidebarCottage();

        updateTotal();

        validateGuests();

        loadBookedDates(0);

        refreshCottageAvailability();

        return;
    }


    document.querySelectorAll(
        '.bw-filter-tab'
    ).forEach(tab => {

        tab.style.display = '';

    });


    document.querySelectorAll(
        '.bw-cat-heading'
    ).forEach(h => {

        h.style.display = '';

    });


    document.querySelectorAll(
        '.bw-cat-group'
    ).forEach(g => {

        g.style.display = '';

    });


    document.querySelectorAll(
        '[data-cat-group]'
    ).forEach(g => {

        g.style.display = '';

    });


    document.querySelectorAll(
        '.bw-cottage-card'
    ).forEach(card => {

        const isRoom =
            card.dataset.category
            === OVERNIGHT_CATEGORY;


        const restricted =
            isOvernight &&
            !isRoom;


        card.style.display =
            restricted
                ? 'none'
                : '';


        const radio =
            card.querySelector(
                'input[type=radio]'
            );


        if (
            radio &&
            restricted
        ) {

            radio.disabled =
                true;
        }

    });


    document.querySelectorAll(
        '.bw-pavilion-accordion'
    ).forEach(acc => {

        acc.classList.remove(
            'is-expanded'
        );

    });


    document.querySelectorAll(
        '[data-cat-heading]'
    ).forEach(h => {

        if (
            isOvernight &&
            h.dataset.catHeading
                !== OVERNIGHT_CATEGORY
        ) {

            h.style.display =
                'none';
        }

    });


    document.querySelectorAll(
        '[data-cat-group]'
    ).forEach(g => {

        if (
            isOvernight &&
            g.dataset.catGroup
                !== OVERNIGHT_CATEGORY
        ) {

            g.style.display =
                'none';
        }

    });


    document.querySelectorAll(
        '.bw-filter-tab'
    ).forEach(tab => {

        const f =
            tab.dataset.filter;


        const hideTab =
            isOvernight &&
            f !== OVERNIGHT_CATEGORY;


        tab.style.display =
            hideTab
                ? 'none'
                : '';

    });


    const activeTab =
        document.querySelector(
            '.bw-filter-tab.is-active'
        );


    if (
        isOvernight &&
        activeTab &&
        activeTab.style.display === 'none'
    ) {

        const roomsTab =
            document.querySelector(
                `.bw-filter-tab[data-filter="${OVERNIGHT_CATEGORY}"]`
            )
            ||
            document.querySelector(
                '.bw-filter-tab[data-filter="all"]'
            );


        if (roomsTab) {
            roomsTab.click();
        }

    } else if (
        !isOvernight &&
        activeTab &&
        activeTab.dataset.filter !== 'all'
    ) {

        activeTab.click();
    }


    updateTotal();

    refreshCottageAvailability();

    loadBookedDates(
        selectedCottageId
    );
}


document.querySelectorAll(
    '#bookingTypeOptions .bw-segmented-btn'
).forEach(label => {

    label.addEventListener(
        'click',
        function() {

            document.querySelectorAll(
                '#bookingTypeOptions .bw-segmented-btn'
            ).forEach(o => {

                o.classList.remove(
                    'is-selected'
                );

            });


            this.classList.add(
                'is-selected'
            );


            bookingType =
                this.dataset.bookingType;


            this.querySelector(
                'input[type=radio]'
            ).checked = true;


            applyBookingTypeUI();

            // Re-check the selected date immediately. If an Exclusive
            // Resort reservation exists for the same date, every
            // accommodation is changed to OCCUPIED/Unavailable.
            if (document.getElementById('check_in').value) {
                refreshCottageAvailability();
                loadBookedDates(selectedCottageId || 0);
            }

        }
    );

});


document.getElementById(
    'bookingForm'
).addEventListener(
    'keydown',
    (e) => {

        if (e.key !== 'Enter') {
            return;
        }


        if (e.target.tagName === 'TEXTAREA') {
            return;
        }


        if (currentStep >= 4) {
            return;
        }


        e.preventDefault();


        if (currentStep === 3) {
            return;
        }


        const nextBtn =
            document.getElementById(
                'toStep' +
                (currentStep + 1)
            );


        if (nextBtn) {
            nextBtn.click();
        }

    }
);


function setGuestCount(n) {

    n =
        Math.max(
            0,
            Math.min(
                200,
                n
            )
        );


    document.getElementById(
        'num_guests'
    ).value = n;


    document.getElementById(
        'guestCountLabel'
    ).textContent =
        n +
        (
            n === 1
                ? ' Guest'
                : ' Guests'
        );


    updateTotal();

    validateGuests();
}


document.getElementById(
    'guestMinus'
).addEventListener(
    'click',
    () => setGuestCount(
        parseInt(
            document.getElementById(
                'num_guests'
            ).value
        ) - 1
    )
);


document.getElementById(
    'guestPlus'
).addEventListener(
    'click',
    () => setGuestCount(
        parseInt(
            document.getElementById(
                'num_guests'
            ).value
        ) + 1
    )
);


function validateGuests() {

    const guestSelect =
        document.getElementById(
            'num_guests'
        );


    const guestCount =
        parseInt(
            guestSelect.value
        );


    let warning =
        document.getElementById(
            'guest-capacity-warning'
        );


    const stepper =
        document.querySelector(
            '.bw-guest-stepper'
        );


    const maxCapacity =
        bookingType === 'Exclusive'
            ? 200
            : selectedCapacity;


    if (
        maxCapacity > 0 &&
        guestCount > maxCapacity
    ) {

        stepper.style.borderColor =
            '#E24B4A';


        if (!warning) {

            warning =
                document.createElement(
                    'p'
                );


            warning.id =
                'guest-capacity-warning';


            warning.style.cssText =
                'color:#A32D2D;background:#FCEBEB;border:1px solid #F09595;border-radius:6px;padding:8px 12px;font-size:0.82rem;margin-top:8px;';


            stepper.insertAdjacentElement(
                'afterend',
                warning
            );
        }


        warning.textContent =
            '⚠️ This booking allows a maximum of ' +
            maxCapacity +
            ' guests. Please lower the number.';
    }

    else {

        stepper.style.borderColor =
            '';


        if (warning) {
            warning.remove();
        }
    }
}


document.querySelectorAll(
    '.bw-filter-tab'
).forEach(tab => {

    tab.addEventListener(
        'click',
        function() {

            document.querySelectorAll(
                '.bw-filter-tab'
            ).forEach(t => {

                t.classList.remove(
                    'is-active'
                );

            });


            this.classList.add(
                'is-active'
            );


            const filter =
                this.dataset.filter;


            const isOvernight =
                bookingType === 'Overnight';


            document.querySelectorAll(
                '[data-cat-group]'
            ).forEach(group => {

                if (
                    isOvernight &&
                    group.dataset.catGroup
                        !== OVERNIGHT_CATEGORY
                ) {

                    group.style.display =
                        'none';

                    return;
                }


                const show =
                    filter === 'all' ||
                    group.dataset.catGroup
                        === filter;


                group.style.display =
                    show
                        ? 'grid'
                        : 'none';

            });


            document.querySelectorAll(
                '[data-cat-heading]'
            ).forEach(h => {

                if (
                    isOvernight &&
                    h.dataset.catHeading
                        !== OVERNIGHT_CATEGORY
                ) {

                    h.style.display =
                        'none';

                    return;
                }


                const show =
                    filter === 'all' ||
                    h.dataset.catHeading
                        === filter;


                h.style.display =
                    show
                        ? 'block'
                        : 'none';

            });

        }
    );

});


const cottageLightbox =
    document.getElementById(
        'cottageLightbox'
    );


const cottageLightboxImg =
    document.getElementById(
        'cottageLightboxImg'
    );


const cottageLightboxCaption =
    document.getElementById(
        'cottageLightboxCaption'
    );


const cottageLightboxClose =
    document.getElementById(
        'cottageLightboxClose'
    );


function openCottageLightbox(
    src,
    caption
) {

    if (!cottageLightbox) {
        return;
    }


    cottageLightboxImg.src =
        src;


    cottageLightboxImg.alt =
        caption || '';


    cottageLightboxCaption.textContent =
        caption || '';


    cottageLightbox.classList.add(
        'is-open'
    );


    document.body.style.overflow =
        'hidden';
}


function closeCottageLightbox() {

    if (!cottageLightbox) {
        return;
    }


    cottageLightbox.classList.remove(
        'is-open'
    );


    document.body.style.overflow =
        '';
}


if (cottageLightboxClose) {

    cottageLightboxClose.addEventListener(
        'click',
        closeCottageLightbox
    );
}


if (cottageLightbox) {

    cottageLightbox.addEventListener(
        'click',
        function(e) {

            if (
                e.target === cottageLightbox
            ) {

                closeCottageLightbox();
            }

        }
    );
}


document.addEventListener(
    'keydown',
    function(e) {

        if (
            cottageLightbox &&
            cottageLightbox.classList.contains(
                'is-open'
            ) &&
            e.key === 'Escape'
        ) {

            closeCottageLightbox();
        }

    }
);


document.getElementById(
    'cottageOptions'
).addEventListener(
    'click',
    function(e) {

        const card =
            e.target.closest(
                '.bw-cottage-card'
            );


        if (!card) {
            return;
        }


        const imgBox =
            e.target.closest(
                '.bw-cc-img'
            );


        if (imgBox) {

            e.preventDefault();


            const clickedImg =
                imgBox.querySelector(
                    'img'
                );


            const thumb =
                clickedImg
                    ? clickedImg.src
                    : card.dataset.thumb;


            if (thumb) {

                openCottageLightbox(
                    thumb,
                    card.dataset.name
                );
            }


            return;
        }


        if (
            card.classList.contains(
                'is-booked'
            )
        ) {
            return;
        }


        const radio =
            card.querySelector(
                'input[type=radio]'
            );


        if (
            !radio ||
            radio.disabled
        ) {
            return;
        }


        document.querySelectorAll(
            '.bw-cottage-card'
        ).forEach(c => {

            c.classList.remove(
                'is-selected'
            );

        });


        document.querySelectorAll(
            '.bw-cottage-card-wrap'
        ).forEach(w => {

            w.classList.remove(
                'is-selected'
            );

        });


        card.classList.add(
            'is-selected'
        );


        const wrap =
            card.closest(
                '.bw-cottage-card-wrap'
            );


        if (wrap) {

            wrap.classList.add(
                'is-selected'
            );
        }


        radio.checked = true;


        selectedCottageId =
            parseInt(
                radio.value
            );


        selectedCapacity =
            parseInt(
                card.dataset.capacity
            );


        selectedCottageName =
            card.dataset.name;


        selectedCategory =
            card.dataset.category;


        const isPavilion =
            selectedCategory ===
            'Pavilion';


        document.querySelectorAll(
            'input[name=event_type]'
        ).forEach(r => {

            r.checked = false;

        });


        document.querySelectorAll(
            '.bw-pavilion-accordion .bw-option-card'
        ).forEach(o => {

            o.classList.remove(
                'is-selected'
            );

        });


        if (isPavilion) {

            populateEventPrices(
                selectedCottageId
            );


            selectedPrice =
                getUnitPrice(
                    selectedCottageId,
                    null
                );

        } else {

            selectedPrice =
                parseFloat(
                    card.dataset.price
                );
        }


        updateSidebarCottage();

        updatePavilionAccordion(
            isPavilion
        );

        updateTotal();

        validateGuests();

        loadBookedDates(
            selectedCottageId
        );

    }
);


function updateSidebarCottage() {

    const exclusive =
        bookingType === 'Exclusive';


    document.getElementById(
        'sidebarEmpty'
    ).style.display =
        selectedCottageId || exclusive
            ? 'none'
            : 'block';


    document.getElementById(
        'sidebarFilled'
    ).style.display =
        selectedCottageId || exclusive
            ? 'block'
            : 'none';


    document.getElementById(
        'summary-cottage-name'
    ).textContent =
        exclusive
            ? 'Exclusive Resort'
            : (
                selectedCottageName ||
                '—'
            );


    document.getElementById(
        'summary-cottage-cat'
    ).textContent =
        exclusive
            ? 'Entire Resort'
            : (
                selectedCategory ||
                '—'
            );


    const card =
        document.querySelector(
            '.bw-cottage-card.is-selected'
        );


    const imgBox =
        document.getElementById(
            'sidebarCottageImg'
        );


    if (exclusive) {

        imgBox.innerHTML =
            '🏝️';

        return;
    }


    const thumb =
        card
            ? card.dataset.thumb
            : '';


    imgBox.innerHTML =
        thumb
            ? `<img src="${thumb}" alt="">`
            : '🏡';
}


function updatePavilionAccordion(
    isPavilion
) {

    document.querySelectorAll(
        '.bw-pavilion-accordion'
    ).forEach(acc => {

        const isThisOne =
            isPavilion &&
            parseInt(
                acc.dataset.pavilionAccordion
            ) === selectedCottageId;


        acc.classList.toggle(
            'is-expanded',
            isThisOne
        );


        acc.querySelectorAll(
            'input[name=event_type]'
        ).forEach(r => {

            r.required =
                isThisOne;

        });

    });
}


document.querySelectorAll(
    '.bw-pavilion-accordion .bw-option-card'
).forEach(label => {

    label.addEventListener(
        'click',
        function(e) {

            e.stopPropagation();


            const group =
                this.closest(
                    '[data-pavilion-events]'
                );


            if (group) {

                group.querySelectorAll(
                    '.bw-option-card'
                ).forEach(o => {

                    o.classList.remove(
                        'is-selected'
                    );

                });
            }


            this.classList.add(
                'is-selected'
            );


            this.querySelector(
                'input[type=radio]'
            ).checked = true;


            selectedPrice =
                getUnitPrice(
                    selectedCottageId,
                    this.dataset.eventType
                );


            updateTotal();

        }
    );

});


{
    const initiallySelected =
        document.querySelector(
            '.bw-cottage-card.is-selected'
        );


    const isPavilion =
        !!initiallySelected &&
        initiallySelected.dataset.category
            === 'Pavilion';


    if (bookingType !== 'Exclusive') {

        updatePavilionAccordion(
            isPavilion
        );


        if (initiallySelected) {

            updateSidebarCottage();


            if (isPavilion) {

                populateEventPrices(
                    selectedCottageId
                );
            }
        }

    } else {

        selectedCottageId = 0;

        selectedPrice = 0;

        selectedCapacity = 200;

        selectedCottageName =
            'Exclusive Resort';

        selectedCategory =
            'Exclusive';

        updateSidebarCottage();
    }
}


function updateTotal() {

    const ci =
        document.getElementById(
            'check_in'
        ).value;


    const co =
        document.getElementById(
            'check_out'
        ).value;


    document.getElementById(
        'summary-guests'
    ).textContent =
        document.getElementById(
            'num_guests'
        ).value +
        ' Guests';


    document.getElementById(
        'summary-checkin-label'
    ).textContent =
        bookingType === 'Day Use'
            ? 'Visit Date'
            : (
                bookingType === 'Exclusive'
                    ? 'Event Start'
                    : 'Check-in'
            );


    const checkoutRow =
        document.getElementById(
            'summary-checkout-row'
        );

    if (checkoutRow) {
        checkoutRow.style.display =
            (bookingType === 'Day Use' ||
             bookingType === 'Exclusive')
                ? 'none'
                : '';
    }


    // Exclusive has one visible event date, but uses the internally
    // calculated next-day checkout only for availability/range logic.
    // Therefore its price and event date must update even if checkout
    // is hidden from the guest.
    if (bookingType === 'Exclusive') {
        if (ci) {
            document.getElementById(
                'summary-checkin'
            ).textContent =
                new Date(ci)
                    .toLocaleDateString(
                        'en-PH',
                        {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        }
                    );
        } else {
            document.getElementById(
                'summary-checkin'
            ).textContent = '—';
        }

        document.getElementById(
            'summary-total'
        ).textContent = '₱40,000.00';

        const hint =
            document.getElementById(
                'gcashAmountHint'
            );

        if (hint) {
            updateExclusiveAmountHint();
        }

        return;
    }


    if (ci && co) {

        const nights =
            Math.round(
                (
                    new Date(co) -
                    new Date(ci)
                )
                /
                86400000
            );


        if (nights > 0) {

            document.getElementById(
                'summary-checkin'
            ).textContent =
                new Date(ci)
                    .toLocaleDateString(
                        'en-PH',
                        {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        }
                    );


            document.getElementById(
                'summary-checkout'
            ).textContent =
                new Date(co)
                    .toLocaleDateString(
                        'en-PH',
                        {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        }
                    );


            const hint =
                document.getElementById(
                    'gcashAmountHint'
                );


            if (bookingType === 'Exclusive') {

                // Exclusive Resort has a fixed Exclusive Resort rental price of ₱40,000.
                const totalStr = '₱40,000.00';
                const depositStr = '₱8,000.00 (20% down payment)';

                document.getElementById(
                    'summary-total'
                ).textContent =
                    totalStr;

                if (hint) {
                    hint.textContent =
                        depositStr;
                }

            } else if (selectedPrice > 0) {

                const total =
                    nights *
                    selectedPrice;

                const totalStr =
                    '₱' +
                    total.toLocaleString(
                        'en-PH',
                        {
                            minimumFractionDigits: 2
                        }
                    );

                document.getElementById(
                    'summary-total'
                ).textContent =
                    totalStr;

                if (hint) {
                    hint.textContent =
                        totalStr;
                }

            } else {

                document.getElementById(
                    'summary-total'
                ).textContent =
                    '₱0.00';

                if (hint) {
                    hint.textContent =
                        'the total amount';
                }

            }

        }

    }

}


function updateReview() {

    const ci =
        document.getElementById(
            'check_in'
        ).value;


    const co =
        document.getElementById(
            'check_out'
        ).value;


    document.getElementById(
        'rev-cottage'
    ).textContent =
        bookingType === 'Exclusive'
            ? 'Exclusive Resort'
            : (
                selectedCottageName ||
                '—'
            );


    document.getElementById(
        'rev-checkin-label'
    ).textContent =
        bookingType === 'Day Use'
            ? 'Visit Date'
            : (
                bookingType === 'Exclusive'
                    ? 'Event Start'
                    : 'Check-in'
            );


    document.getElementById(
        'rev-checkout-row'
    ).style.display =
        (bookingType === 'Day Use' ||
         bookingType === 'Exclusive')
            ? 'none'
            : '';


    document.getElementById(
        'rev-checkin'
    ).textContent =
        ci
            ? new Date(ci)
                .toLocaleDateString(
                    'en-PH',
                    {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    }
                )
            : '—';


    document.getElementById(
        'rev-checkout'
    ).textContent =
        co
            ? new Date(co)
                .toLocaleDateString(
                    'en-PH',
                    {
                        month: 'short',
                        day: 'numeric',
                        year: 'numeric'
                    }
                )
            : '—';


    document.getElementById(
        'rev-guests'
    ).textContent =
        document.getElementById(
            'num_guests'
        ).value;


    document.getElementById(
        'rev-name'
    ).textContent =
        document.querySelector(
            'input[name=guest_name]'
        ).value.trim()
        || '—';


    let nights = 0;


    if (ci && co) {

        nights =
            Math.max(
                0,
                Math.round(
                    (
                        new Date(co) -
                        new Date(ci)
                    )
                    /
                    86400000
                )
            );
    }


    if (bookingType === 'Day Use') {

        document.getElementById(
            'rev-nights-label'
        ).textContent =
            'Type';


        document.getElementById(
            'rev-nights'
        ).textContent =
            ci
                ? 'Day Use'
                : '—';

    } else if (
        bookingType === 'Exclusive'
    ) {

        document.getElementById(
            'rev-nights-label'
        ).textContent =
            'Type';


        document.getElementById(
            'rev-nights'
        ).textContent =
            'Exclusive Resort';

    } else {

        document.getElementById(
            'rev-nights-label'
        ).textContent =
            'Type';


        document.getElementById(
            'rev-nights'
        ).textContent =
            nights ? 'Overnight (' + nights + ' night' + (nights > 1 ? 's' : '') + ')' : '—';
    }


    const total =
        bookingType === 'Exclusive'
            ? 40000
            : nights * selectedPrice;


    document.getElementById(
        'rev-total'
    ).textContent =
        '₱' +
        total.toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2
            }
        );
}


let availabilityRequestSeq = 0;


function refreshCottageAvailability() {

    const ci =
        document.getElementById(
            'check_in'
        ).value;


    let co =
        document.getElementById(
            'check_out'
        ).value;


    // Day Use and Exclusive use one visible date.
    // Always build the internal next-day range before checking
    // availability so an Exclusive booking on that date blocks
    // every cottage, room, and pavilion.
    if (
        ci &&
        (bookingType === 'Day Use' || bookingType === 'Exclusive')
    ) {
        syncDayUseCheckout();
        co = document.getElementById('check_out').value;
    }


    const statusEl =
        document.getElementById(
            'availabilityStatus'
        );


    if (
        !ci ||
        !co ||
        co <= ci
    ) {

        if (statusEl) {
            statusEl.textContent =
                '';
        }

        return;
    }


    const thisRequest =
        ++availabilityRequestSeq;


    if (statusEl) {

        statusEl.textContent =
            'Checking availability for your dates…';
    }


    fetch(
        `booking.php?action=check_availability&check_in=${encodeURIComponent(ci)}&check_out=${encodeURIComponent(co)}`
    )

    .then(res => res.json())

    .then(data => {

        if (
            thisRequest !==
            availabilityRequestSeq
        ) {
            return;
        }


        if (!data.success) {

            if (statusEl) {
                statusEl.textContent =
                    '';
            }

            return;
        }


        let anyDeselected =
            false;

        // If the selected date has an Exclusive Resort booking,
        // Day Use and Overnight must show ALL accommodations as unavailable.
        const exclusiveActive =
            data.exclusive_active === true;


        document.querySelectorAll(
            '.bw-cottage-card'
        ).forEach(card => {

            const radio =
                card.querySelector(
                    'input[type=radio]'
                );


            if (!radio) {
                return;
            }


            const status = exclusiveActive
                ? 'booked'
                : data.availability[radio.value];


            const tag =
                card.querySelector(
                    '.bw-cc-select'
                );


            const badge =
                card.querySelector(
                    '.bw-cc-badge'
                );


            if (status === 'booked') {

                radio.disabled =
                    true;


                card.classList.add(
                    'is-booked'
                );


                if (tag) {
                    tag.textContent =
                        'Unavailable';
                }


                if (badge) {

                    badge.textContent =
                        'Occupied';

                    badge.classList.add(
                        'is-booked'
                    );
                }


                if (radio.checked) {

                    radio.checked =
                        false;


                    card.classList.remove(
                        'is-selected'
                    );


                    anyDeselected =
                        true;
                }

            } else if (
                status === 'available'
            ) {

                const restricted =
                    bookingType === 'Overnight' &&
                    card.dataset.category
                        !== OVERNIGHT_CATEGORY;


                radio.disabled =
                    restricted;


                card.classList.remove(
                    'is-booked'
                );


                if (tag) {
                    tag.textContent =
                        'Select';
                }


                if (badge) {

                    badge.textContent =
                        'Available';

                    badge.classList.remove(
                        'is-booked'
                    );
                }

            }

        });


        if (anyDeselected) {

            selectedCottageId = 0;

            selectedPrice = 0;

            selectedCapacity = 0;

            selectedCottageName = '';

            selectedCategory = '';


            updateSidebarCottage();


            document.getElementById(
                'summary-total'
            ).textContent =
                '₱0.00';


            if (statusEl) {

                statusEl.textContent =
                    '⚠️ Your previously selected cottage is booked for these dates — please choose another.';
            }


            loadBookedDates(0);

        } else if (
            statusEl
        ) {

            statusEl.textContent =
                '✓ Availability updated for your selected dates.';
        }


        validateGuests();

    })

    .catch(err => {

        console.error(
            'Availability check failed:',
            err
        );


        if (statusEl) {
            statusEl.textContent =
                '';
        }

    });
}


function parseISO(s) {

    const [y, m, d] =
        s.split('-')
         .map(Number);


    return new Date(
        Date.UTC(
            y,
            m - 1,
            d
        )
    );
}


function toISO(date) {

    return date
        .toISOString()
        .slice(0, 10);
}


function addDaysISO(
    s,
    n
) {

    const d =
        parseISO(s);


    d.setUTCDate(
        d.getUTCDate() + n
    );


    return toISO(d);
}


function formatRangeLabel(
    startISO,
    endISOExclusive
) {

    const start =
        parseISO(startISO);


    const lastDay =
        parseISO(endISOExclusive);


    lastDay.setUTCDate(
        lastDay.getUTCDate() - 1
    );


    const opts = {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC'
    };


    const startLabel =
        start.toLocaleDateString(
            'en-US',
            opts
        );


    if (
        toISO(start) ===
        toISO(lastDay)
    ) {

        return startLabel;
    }


    const sameMonth =
        start.getUTCMonth() ===
            lastDay.getUTCMonth()
        &&
        start.getUTCFullYear() ===
            lastDay.getUTCFullYear();


    return sameMonth
        ? `${startLabel}–${lastDay.getUTCDate()}`
        : `${startLabel} – ${lastDay.toLocaleDateString('en-US', opts)}`;
}


function collapseDatesToRanges(
    sortedISODates
) {

    const ranges = [];

    let rangeStart = null;
    let prev = null;


    sortedISODates.forEach(
        iso => {

            if (rangeStart === null) {

                rangeStart = iso;

            } else if (
                addDaysISO(
                    prev,
                    1
                ) !== iso
            ) {

                ranges.push([
                    rangeStart,
                    addDaysISO(
                        prev,
                        1
                    )
                ]);


                rangeStart = iso;
            }


            prev = iso;

        }
    );


    if (rangeStart !== null) {

        ranges.push([
            rangeStart,
            addDaysISO(
                prev,
                1
            )
        ]);
    }


    return ranges;
}


function renderUnavailableNote(
    data
) {

    const note =
        document.getElementById(
            'unavailableDatesNote'
        );


    if (!note) {
        return;
    }


    if (
        !data ||
        !data.success
    ) {

        note.textContent =
            '';

        return;
    }


    let ranges;
    let prefix;


    if (
        data.mode ===
        'cottage'
    ) {

        ranges =
            (data.ranges || [])
                .map(
                    r => [
                        r.check_in,
                        r.check_out
                    ]
                );


        prefix =
            selectedCottageName
                ? `Unavailable for ${selectedCottageName}: `
                : 'Unavailable: ';

    } else {

        ranges =
            collapseDatesToRanges(
                (data.fully_booked_dates || [])
                    .slice()
                    .sort()
            );


        prefix =
            'Fully booked resort-wide (no cottages free): ';
    }


    if (ranges.length === 0) {

        note.textContent =
            '';

        return;
    }


    note.textContent =
        '🔴 ' +
        prefix +
        ranges
            .map(
                ([s, e]) =>
                    formatRangeLabel(
                        s,
                        e
                    )
            )
            .join(', ');
}


function loadBookedDates(
    cottageId
) {

    const url =
        cottageId
            ? `booking.php?action=get_booked_dates&cottage_id=${cottageId}`
            : `booking.php?action=get_booked_dates`;


    return fetch(url)

        .then(res => res.json())

        .then(data =>
            renderUnavailableNote(
                data
            )
        )

        .catch(err =>
            console.error(
                'Could not load booked dates:',
                err
            )
        );
}


document.getElementById(
    'check_in'
).addEventListener(
    'change',
    function() {

        const d =
            new Date(
                this.value
            );


        d.setDate(
            d.getDate() + 1
        );


        document.getElementById(
            'check_out'
        ).min =
            d.toISOString()
             .split('T')[0];


        if (
            bookingType === 'Day Use' ||
            bookingType === 'Exclusive'
        ) {

            syncDayUseCheckout();
        }


        updateTotal();

        refreshCottageAvailability();

    }
);


document.getElementById(
    'check_out'
).addEventListener(
    'change',
    function() {

        updateTotal();

        refreshCottageAvailability();

    }
);


function setGcashFieldsRequired(
    isManual
) {

    [
        'gcash_reference',
        'gcash_sender_name'
    ].forEach(id => {

        document.getElementById(
            id
        ).required =
            isManual;

    });


    document.getElementById(
        'gcash_proof'
    ).required =
        isManual;
}


function updateExclusiveAmountHint() {

    const hint =
        document.getElementById('gcashAmountHint');

    const typeRadio =
        document.querySelector('input[name=booking_type]:checked');

    if (!hint || !typeRadio || typeRadio.value !== 'Exclusive') {
        return;
    }

    const opt =
        document.querySelector('input[name=payment_option]:checked');

    hint.textContent =
        (opt && opt.value === 'Full')
            ? '₱40,000.00 (full payment)'
            : '₱8,000.00 (20% down payment)';
}

document.querySelectorAll(
    'input[name=payment_option]'
).forEach(r => r.addEventListener(
    'change',
    updateExclusiveAmountHint
));


function applyPaymentMethod(
    value
) {

    const isArrival =
        value ===
        'Pay on Arrival';


    const isManual =
        value ===
        'GCash';


    document.getElementById(
        'arrivalForm'
    ).style.display =
        isArrival
            ? 'block'
            : 'none';


    document.getElementById(
        'gcashForm'
    ).style.display =
        isManual
            ? 'block'
            : 'none';


    document.getElementById(
        'submitBtn'
    ).textContent =
        isManual
            ? 'Submit Booking + Payment Proof 💵'
            : 'Confirm Reservation 🏨';


    document.getElementById(
        'formNote'
    ).textContent =
        isManual
            ? 'Pay via GCash first, then submit — your booking will show as Pending Verification until confirmed.'
            : 'No payment required online. Settle in cash or GCash upon arrival.';


    setGcashFieldsRequired(
        isManual
    );
}


document.querySelectorAll(
    'input[name=payment_method]'
).forEach(radio => {

    radio.addEventListener(
        'change',
        function() {

            document.querySelectorAll(
                '#opt-arrival, #opt-gcash'
            ).forEach(o => {

                o.classList.remove(
                    'is-selected'
                );

            });


            this.closest(
                '.bw-option-card'
            ).classList.add(
                'is-selected'
            );


            applyPaymentMethod(
                this.value
            );

        }
    );

});


document.getElementById(
    'gcash_proof'
).addEventListener(
    'change',
    function() {

        const preview =
            document.getElementById(
                'gcashProofPreview'
            );


        const file =
            this.files &&
            this.files[0];


        if (!file) {

            preview.style.display =
                'none';

            preview.src =
                '';

            return;
        }


        const reader =
            new FileReader();


        reader.onload =
            e => {

                preview.src =
                    e.target.result;

                preview.style.display =
                    'block';
            };


        reader.readAsDataURL(
            file
        );

    }
);


const checkedPaymentMethod =
    document.querySelector(
        'input[name=payment_method]:checked'
    );


if (checkedPaymentMethod) {

    applyPaymentMethod(
        checkedPaymentMethod.value
    );
}


validateGuests();

applyBookingTypeUI();

setGuestCount(
    parseInt(
        document.getElementById(
            'num_guests'
        ).value
    )
);

<?php endif; ?>

</script>


<script src="js/main.js"></script>

</body>
</html>