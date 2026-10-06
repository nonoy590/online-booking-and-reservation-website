<?php
require_once 'auth.php';
$page_title = 'Reservations';
$db = getDB();
$msg = '';

if (isset($_GET['cancelled'])) {
    $msg = 'Reservation cancelled successfully. The accommodation is now <strong>Available</strong> for those dates.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['Confirmed', 'Cancelled', 'Pending'])) {
        if ($action === 'Cancelled') {
            // IMPORTANT: keep the cottage record itself available.
            // Availability is calculated from reservation status, so a
            // Cancelled reservation must never block the selected dates.
            $stmt = $db->prepare("
                UPDATE reservations
                SET status = 'Cancelled',
                    cancelled_by = 'Admin',
                    cancelled_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $msg = "Reservation cancelled successfully. The accommodation is now available again for those dates.";
            header('Location: reservations.php?cancelled=1');
            exit;
        } else {
            $stmt = $db->prepare("
                UPDATE reservations
                SET status = ?, cancelled_by=NULL, cancelled_at=NULL
                WHERE id = ?
            ");
        }

        $stmt->execute([$action, $id]);
        $msg = "Reservation updated to <strong>$action</strong> successfully.";
    }
}

// Payment status is no longer toggled by hand: it is derived from the
// verified GCash payments (see syncReservationPayment() in includes/config.php).

$status_filter = clean($_GET['status'] ?? '');
$search        = clean($_GET['search'] ?? '');
$view_id       = (int)($_GET['view'] ?? 0);

$where = [];
$params = [];

if ($status_filter) {
    $where[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[] = "(r.booking_code LIKE ? OR r.guest_name LIKE ? OR r.guest_email LIKE ?)";
    $params = array_merge(
        $params,
        ["%$search%", "%$search%", "%$search%"]
    );
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reservations = $db->prepare("
    SELECT
        r.*,
        COALESCE(
            c.name,
            CASE
                WHEN r.booking_type IN ('Exclusive', 'Exclusive Resort')
                    THEN 'Exclusive Resort'
                ELSE 'Accommodation'
            END
        ) AS cottage_name,
        c.price_per_night
    FROM reservations r
    LEFT JOIN cottages c ON r.cottage_id = c.id
    $where_sql
    ORDER BY r.created_at DESC
");

$reservations->execute($params);
$reservations = $reservations->fetchAll();

$viewing = null;

if ($view_id) {
    $stmt = $db->prepare("
        SELECT
            r.*,
            COALESCE(
                c.name,
                CASE
                    WHEN r.booking_type IN ('Exclusive', 'Exclusive Resort')
                        THEN 'Exclusive Resort'
                    ELSE 'Accommodation'
                END
            ) AS cottage_name,
            c.description AS cottage_desc,
            c.capacity
        FROM reservations r
        LEFT JOIN cottages c ON r.cottage_id = c.id
        WHERE r.id = ?
    ");

    $stmt->execute([$view_id]);
    $viewing = $stmt->fetch();
}

include 'partials/header.php';
?>

<?php if ($msg): ?>
<div class="alert-success">
    <?= $msg ?>
</div>
<?php endif; ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Reservations — S-Five Resort</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="admin.css">
</head>

<?php if ($viewing): ?>

<?php
$booking_type = trim($viewing['booking_type'] ?? '');

$is_exclusive = in_array(
    strtolower($booking_type),
    ['exclusive', 'exclusive resort'],
    true
);

$is_day_use = ($booking_type === 'Day Use');
?>

<div class="card" style="margin-bottom:2rem;">

    <div class="card-header">

        <h3>
            Reservation — <?= htmlspecialchars($viewing['booking_code']) ?>
        </h3>

        <a href="reservations.php" class="btn-sm">
            ← Back to List
        </a>

    </div>

    <div class="card-body">

        <div class="detail-grid">

            <div class="detail-section">

                <h4>Guest Information</h4>

                <div class="detail-row">
                    <span>Name</span>
                    <strong>
                        <?= htmlspecialchars($viewing['guest_name']) ?>
                    </strong>
                </div>

                <div class="detail-row">
                    <span>Email</span>
                    <strong>
                        <?= htmlspecialchars($viewing['guest_email']) ?>
                    </strong>
                </div>

                <div class="detail-row">
                    <span>Phone</span>
                    <strong>
                        <?= htmlspecialchars($viewing['guest_phone']) ?>
                    </strong>
                </div>

                <div class="detail-row">
                    <span>Guests</span>
                    <strong>
                        <?= $viewing['num_guests'] ?>
                    </strong>
                </div>

                <?php if ($viewing['special_requests']): ?>

                <div class="detail-row">
                    <span>Special Requests</span>
                    <strong>
                        <?= htmlspecialchars($viewing['special_requests']) ?>
                    </strong>
                </div>

                <?php endif; ?>

            </div>


            <div class="detail-section">

                <h4>Booking Details</h4>


                <div class="detail-row">

                    <span>
                        <?= $is_exclusive ? 'Exclusive' : 'Cottage' ?>
                    </span>

                    <strong>
                        <?= $is_exclusive
                            ? 'Exclusive'
                            : htmlspecialchars($viewing['cottage_name'])
                        ?>
                    </strong>

                </div>


                <?php if ($is_exclusive): ?>

                <div class="detail-row">

                    <span>Booking Type</span>

                    <strong>
                        🏝️ Exclusive Resort
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Visit Date</span>

                    <strong>
                        <?= date(
                            'F d, Y',
                            strtotime($viewing['check_in'])
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Duration</span>

                    <strong>
                        🏝️ Exclusive Resort (whole resort)
                    </strong>

                </div>


                <?php elseif ($is_day_use): ?>

                <div class="detail-row">

                    <span>Visit Date</span>

                    <strong>
                        <?= date(
                            'F d, Y',
                            strtotime($viewing['check_in'])
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Duration</span>

                    <strong>
                        ☀️ Day Use (single day)
                    </strong>

                </div>


                <?php else: ?>

                <div class="detail-row">

                    <span>Check-in</span>

                    <strong>
                        <?= date(
                            'F d, Y',
                            strtotime($viewing['check_in'])
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Check-out</span>

                    <strong>
                        <?= date(
                            'F d, Y',
                            strtotime($viewing['check_out'])
                        ) ?>
                    </strong>

                </div>

                <?php
                $nights =
                    (
                        strtotime($viewing['check_out']) -
                        strtotime($viewing['check_in'])
                    ) / 86400;
                ?>

                <div class="detail-row">

                    <span>Duration</span>

                    <strong>
                        🌙 <?= $nights ?> night(s)
                    </strong>

                </div>

                <?php endif; ?>


                <div class="detail-row">

                    <span>Total Price</span>

                    <strong>
                        ₱<?= number_format(
                            $viewing['total_price'],
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="detail-row">

                    <span>Payment Method</span>

                    <strong>
                        <?= htmlspecialchars(
                            $viewing['payment_method']
                        ) ?>
                    </strong>

                </div>


                <div class="detail-row">

                    <span>Payment</span>

                    <strong>
                        <?= htmlspecialchars(
                            $viewing['payment_status']
                        ) ?>
                    </strong>

                </div>


                <?php
                    $v_collected = reservationCollected($db, $viewing['id']);
                    $v_balance   = max(0, round((float)$viewing['total_price'] - $v_collected, 2));
                ?>

                <div class="detail-row">
                    <span>Collected (verified GCash)</span>
                    <strong>₱<?= number_format($v_collected, 2) ?></strong>
                </div>

                <div class="detail-row">
                    <span>Balance<?= !empty($viewing['balance_due_date']) ? ' (due ' . date('M j, Y', strtotime($viewing['balance_due_date'])) . ')' : '' ?></span>
                    <strong>₱<?= number_format($v_balance, 2) ?></strong>
                </div>

                <?php if (!empty($viewing['event_type'])): ?>

                <div class="detail-row">

                    <span>Event Type</span>

                    <strong>
                        <?= htmlspecialchars(
                            $viewing['event_type']
                        ) ?>
                    </strong>

                </div>

                <?php endif; ?>


                <div class="detail-row">

                    <span>Booked On</span>

                    <strong>
                        <?= date(
                            'M d, Y g:i A',
                            strtotime($viewing['created_at'])
                        ) ?>
                    </strong>

                </div>


                <?php if (
                    $viewing['status'] === 'Cancelled'
                    && !empty($viewing['cancelled_at'])
                ): ?>

                <div class="detail-row">

                    <span>Cancelled</span>

                    <strong>
                        <?= date(
                            'M d, Y g:i A',
                            strtotime($viewing['cancelled_at'])
                        ) ?>

                        (by
                        <?= htmlspecialchars(
                            $viewing['cancelled_by'] ?? 'Admin'
                        ) ?>)
                    </strong>

                </div>

                <?php endif; ?>

            </div>

        </div>


        <?php if (
            $viewing['status'] === 'Cancelled'
            && in_array($viewing['payment_status'], ['Paid', 'Partially Paid'], true)
        ): ?>

        <div
            class="alert-error"
            style="
                margin-top:1rem;
                background:#fff3cd;
                border-color:#ffe08a;
                color:#7a5c00;
            "
        >

            ⚠️ This booking was
            <strong>paid</strong>
            before it was cancelled — a refund may be owed.

        </div>

        <?php endif; ?>


        <div class="action-bar">

            <strong>

                Status:

                <span class="badge badge-<?= strtolower(
                    $viewing['status']
                ) ?>">

                    <?= htmlspecialchars(
                        $viewing['status']
                    ) ?>

                </span>

            </strong>


            <div class="action-btns">


                <form method="POST" style="display:inline;">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $viewing['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="Confirmed"
                    >

                    <button
                        type="submit"
                        class="btn-action approve"
                        <?= $viewing['status'] === 'Confirmed'
                            ? 'disabled'
                            : '' ?>
                    >
                        ✅ Confirm
                    </button>

                </form>


                <form method="POST" style="display:inline;">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $viewing['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="Cancelled"
                    >

                    <button
                        type="submit"
                        class="btn-action reject"
                        <?= $viewing['status'] === 'Cancelled'
                            ? 'disabled'
                            : '' ?>
                    >
                        ❌ Cancel
                    </button>

                </form>


                <form method="POST" style="display:inline;">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $viewing['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="Pending"
                    >

                    <button
                        type="submit"
                        class="btn-action neutral"
                        <?= $viewing['status'] === 'Pending'
                            ? 'disabled'
                            : '' ?>
                    >
                        ⏳ Set Pending
                    </button>

                </form>
</div>

        </div>

    </div>

</div>

<?php endif; ?>


<div class="card">

    <div class="card-header">

        <h3>
            All Reservations

            <span class="count-badge">
                <?= count($reservations) ?>
            </span>

        </h3>

    </div>


    <div class="card-body">

        <div class="filter-bar">

            <form
                method="GET"
                class="filter-form"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search by name, email, code..."
                    value="<?= htmlspecialchars($search) ?>"
                >


                <select name="status">

                    <option value="">
                        All Statuses
                    </option>

                    <option
                        value="Pending"
                        <?= $status_filter === 'Pending'
                            ? 'selected'
                            : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="Confirmed"
                        <?= $status_filter === 'Confirmed'
                            ? 'selected'
                            : '' ?>
                    >
                        Confirmed
                    </option>

                    <option
                        value="Cancelled"
                        <?= $status_filter === 'Cancelled'
                            ? 'selected'
                            : '' ?>
                    >
                        Cancelled
                    </option>

                </select>


                <button
                    type="submit"
                    class="btn-filter"
                >
                    Filter
                </button>


                <?php if ($search || $status_filter): ?>

                <a
                    href="reservations.php"
                    class="btn-filter-clear"
                >
                    Clear
                </a>

                <?php endif; ?>

            </form>

        </div>


        <div class="table-wrap">

        <table class="admin-table">

            <thead>

                <tr>

                    <th>Code</th>
                    <th>Guest</th>
                    <th>Type</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Guests</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>


                <?php if (empty($reservations)): ?>

                <tr>

                    <td
                        colspan="10"
                        class="empty-row"
                    >
                        No reservations found.
                    </td>

                </tr>

                <?php endif; ?>


                <?php foreach ($reservations as $r): ?>

                <?php
                $row_type = trim(
                    $r['booking_type'] ?? ''
                );

                $row_exclusive = in_array(
                    strtolower($row_type),
                    ['exclusive', 'exclusive resort'],
                    true
                );
                ?>

                <tr>


                    <td>

                        <code>
                            <?= htmlspecialchars(
                                $r['booking_code']
                            ) ?>
                        </code>

                    </td>


                    <td>

                        <strong>
                            <?= htmlspecialchars(
                                $r['guest_name']
                            ) ?>
                        </strong>

                        <br>

                        <small>
                            <?= htmlspecialchars(
                                $r['guest_email']
                            ) ?>
                        </small>

                    </td>


                    <td>

                        <?php if ($row_exclusive): ?>

                            🏝️ Exclusive Resort

                        <?php elseif ($row_type === 'Day Use'): ?>

                            ☀️ Day Use

                        <?php else: ?>

                            🌙 Overnight

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= date(
                            'M d, Y',
                            strtotime($r['check_in'])
                        ) ?>

                    </td>


                    <td>

                        <?php if ($row_exclusive): ?>

                            <?= date(
                                'M d, Y',
                                strtotime($r['check_in'])
                            ) ?>

                        <?php else: ?>

                            <?= date(
                                'M d, Y',
                                strtotime($r['check_out'])
                            ) ?>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= $r['num_guests'] ?>

                    </td>


                    <td>

                        ₱<?= number_format(
                            $r['total_price'],
                            0
                        ) ?>

                    </td>


                    <td>

                        <span
                            class="badge badge-<?= $r['payment_status'] === 'Paid'
                                ? 'confirmed'
                                : 'pending' ?>"
                        >

                            <?= htmlspecialchars(
                                $r['payment_status']
                            ) ?>

                        </span>

                    </td>


                    <td>

                        <span
                            class="badge badge-<?= strtolower(
                                $r['status']
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $r['status']
                            ) ?>

                        </span>


                        <?php if (
                            $r['status'] === 'Cancelled'
                            && in_array($r['payment_status'], ['Paid', 'Partially Paid'], true)
                        ): ?>

                        <br>

                        <small style="color:#7a5c00;">

                            ⚠️ Refund owed

                        </small>

                        <?php endif; ?>

                    </td>


                    <td>

                        <div class="inline-actions">


                            <a
                                href="reservations.php?view=<?= $r['id'] ?>"
                                class="btn-sm"
                            >
                                View
                            </a>


                            <?php if ($r['status'] === 'Pending'): ?>


                            <form
                                method="POST"
                                style="display:inline;"
                            >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $r['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="Confirmed"
                                >

                                <button
                                    type="submit"
                                    class="btn-sm btn-sm-approve"
                                >
                                    ✅
                                </button>

                            </form>


                            <form
                                method="POST"
                                style="display:inline;"
                            >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= $r['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="Cancelled"
                                >

                                <button
                                    type="submit"
                                    class="btn-sm btn-sm-reject"
                                >
                                    ❌
                                </button>

                            </form>


                            <?php endif; ?>

                        </div>

                    </td>

                </tr>

                <?php endforeach; ?>


            </tbody>

        </table>

        </div>

    </div>

</div>


<?php include 'partials/footer.php'; ?>