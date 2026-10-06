<?php
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sfive_resort');

define('SITE_NAME', 'S-Five Inland Resort');
define('SITE_URL', 'http://localhost/sfive');

// Only cottages in this category may be booked "Overnight" (a real
// multi-night stay). Every other category — Cottages and the Pavilion —
// is Day Use only (a single day, no overnight stay).
define('OVERNIGHT_CATEGORY', 'Rooms');

// managed live from Admin > Settings (see includes/gcash.php).
// Exclusive Resort payment rules: guest pays in full OR a down payment now,
// and settles the balance via GCash on/before (event date - N days).
define('EXCLUSIVE_DOWNPAYMENT_RATE', 0.20);
define('BALANCE_DUE_DAYS_BEFORE',    0);   // 0 = balance due on the event date

define('GCASH_NUMBER',       '09XX-XXX-XXXX');
define('GCASH_ACCOUNT_NAME', 'S-Five Inland Resort');

// Brevo (email) — InfinityFree blocks SMTP, so we send via Brevo's HTTP API.
// Get a free key at https://app.brevo.com/settings/keys/api
define('BREVO_API_KEY',      'PASTE_BREVO_API_KEY_HERE');   // TODO: paste your Brevo API key
define('BREVO_SENDER_EMAIL', 'sender@example.com'); // TODO: must be a verified sender in Brevo
define('BREVO_SENDER_NAME',  'S-Five Inland Resort');

// The one admin account allowed to create/delete other admin accounts
define('MAIN_ADMIN_EMAIL', 'admin@example.com');

function isMainAdmin($email) {
    return strcasecmp((string)$email, MAIN_ADMIN_EMAIL) === 0;
}

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:2rem;color:red;">
                <h2>Database Connection Error</h2>
                <p>Could not connect to MySQL. Please check your config.php settings.</p>
                <code>' . htmlspecialchars($e->getMessage()) . '</code>
            </div>');
        }
        ensureBookingTypeColumn($pdo);
        ensurePaymentTrackingColumns($pdo);
        ensureAdminOtpTable($pdo);
        ensureGalleryTable($pdo);
    }
    return $pdo;
}

function ensureGalleryTable($db) {
    static $checked = false;
    if ($checked) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `gallery_images` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `category` varchar(50) NOT NULL DEFAULT 'Pool',
          `filename` varchar(255) NOT NULL,
          `caption` varchar(150) DEFAULT NULL,
          `sort_order` int(11) NOT NULL DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $checked = true;
}

// Fixed list of gallery categories used by both the admin uploader and the
// public filter buttons — keep these in sync.
function galleryCategories() {
    return ['Pool', 'Garden', 'Cottages & Rooms', 'Pavilion & Events', 'POV / Drone', 'Facilities'];
}

// Adds the booking_type column to reservation if it's missing, so the
// Day Use / Overnight feature works even on a database that was set up
function ensureBookingTypeColumn($db) {
    static $checked = false;
    if ($checked) return;
    $col = $db->query("SHOW COLUMNS FROM reservations LIKE 'booking_type'")->fetch();
    if (!$col) {
        $db->exec("
            ALTER TABLE reservations
            ADD COLUMN booking_type ENUM('Day Use','Overnight','Exclusive') NOT NULL DEFAULT 'Overnight' AFTER check_out
        ");
    } elseif (strpos($col['Type'], 'Exclusive') === false) {
        // Older databases: enum lacked 'Exclusive', so Exclusive bookings were
        // saved blank and showed as Overnight. Add it and repair those rows.
        try {
            $db->exec("ALTER TABLE reservations
                        MODIFY booking_type ENUM('Day Use','Overnight','Exclusive') NOT NULL DEFAULT 'Overnight'");
            $db->exec("UPDATE reservations SET booking_type = 'Exclusive'
                        WHERE booking_type = '' AND cottage_id IS NULL");
        } catch (Throwable $e) {
            error_log('ensureBookingTypeColumn: ' . $e->getMessage());
        }
    }
    $checked = true;
}

// Adds the columns needed for down payment / balance tracking if missing.
// (Same changes as migration_exclusive_downpayment.sql — safe to run twice.)
function ensurePaymentTrackingColumns($db) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        if (!$db->query("SHOW COLUMNS FROM reservations LIKE 'payment_option'")->fetch()) {
            $db->exec("ALTER TABLE reservations
                       ADD COLUMN payment_option ENUM('Full','Downpayment') NOT NULL DEFAULT 'Full'");
        }
        if (!$db->query("SHOW COLUMNS FROM reservations LIKE 'balance_due_date'")->fetch()) {
            $db->exec("ALTER TABLE reservations ADD COLUMN balance_due_date DATE NULL");
        }
        $ps = $db->query("SHOW COLUMNS FROM reservations LIKE 'payment_status'")->fetch();
        if ($ps && strpos($ps['Type'], 'Partially Paid') === false) {
            $db->exec("ALTER TABLE reservations
                       MODIFY payment_status ENUM('Unpaid','Pending Verification','Partially Paid','Paid') DEFAULT 'Unpaid'");
        }
        if (!$db->query("SHOW COLUMNS FROM gcash_payments LIKE 'payment_type'")->fetch()) {
            $db->exec("ALTER TABLE gcash_payments
                       ADD COLUMN payment_type ENUM('Full','Downpayment','Balance') NOT NULL DEFAULT 'Full'");
        }
    } catch (Throwable $e) {
        error_log('ensurePaymentTrackingColumns: ' . $e->getMessage());
    }
}

// Verified GCash money, de-duplicated by reference number (old PayMongo
// polling left several identical rows for one payment).
function verifiedPaymentsSql() {
    return "(SELECT reservation_id, reference_number,
                    MAX(amount) AS amount,
                    MAX(COALESCE(verified_at, submitted_at)) AS paid_at
             FROM gcash_payments
             WHERE status = 'Verified'
             GROUP BY reservation_id, reference_number)";
}

function reservationCollected($db, $reservation_id) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM " . verifiedPaymentsSql() . " vp WHERE vp.reservation_id = ?");
    $stmt->execute([(int)$reservation_id]);
    return (float)$stmt->fetchColumn();
}

// Re-derives reservations.payment_status from the verified payments.
function syncReservationPayment($db, $reservation_id) {
    $r = $db->prepare("SELECT total_price, status FROM reservations WHERE id = ?");
    $r->execute([(int)$reservation_id]);
    $res = $r->fetch();
    if (!$res || $res['status'] === 'Cancelled') return null;   // keep history on cancelled bookings

    $collected = reservationCollected($db, $reservation_id);
    if ($collected + 0.005 >= (float)$res['total_price']) {
        $status = 'Paid';
    } elseif ($collected > 0) {
        $status = 'Partially Paid';
    } else {
        $p = $db->prepare("SELECT COUNT(*) FROM gcash_payments WHERE reservation_id = ? AND status = 'Pending'");
        $p->execute([(int)$reservation_id]);
        $status = ((int)$p->fetchColumn() > 0) ? 'Pending Verification' : 'Unpaid';
    }
    $db->prepare("UPDATE reservations SET payment_status = ? WHERE id = ?")->execute([$status, (int)$reservation_id]);
    return $status;
}

function balanceDueDate($check_in) {
    return date('Y-m-d', strtotime($check_in . ' -' . (int)BALANCE_DUE_DAYS_BEFORE . ' days'));
}

function generateBookingCode() {
    return 'SFR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

function clean($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function ensurePavilionEventPricesTable($db) {
    static $checked = false;
    if ($checked) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `pavilion_event_prices` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `cottage_id` int(11) NOT NULL,
          `event_type` enum('Party Event','Birthday Event','Marriage Event') NOT NULL,
          `price_per_night` decimal(10,2) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `cottage_event` (`cottage_id`,`event_type`),
          CONSTRAINT `fk_pep_cottage` FOREIGN KEY (`cottage_id`) REFERENCES `cottages` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $checked = true;
}

function getPavilionEventPrices($db, $cottage_id = null) {
    ensurePavilionEventPricesTable($db);
    if ($cottage_id) {
        $stmt = $db->prepare("SELECT event_type, price_per_night FROM pavilion_event_prices WHERE cottage_id = ?");
        $stmt->execute([$cottage_id]);
        $prices = [];
        foreach ($stmt->fetchAll() as $row) $prices[$row['event_type']] = (float)$row['price_per_night'];
        return $prices;
    }
    $stmt = $db->query("SELECT cottage_id, event_type, price_per_night FROM pavilion_event_prices");
    $prices = [];
    foreach ($stmt->fetchAll() as $row) {
        $prices[$row['cottage_id']][$row['event_type']] = (float)$row['price_per_night'];
    }
    return $prices;
}

function ensureAdminOtpTable($db) {
    static $checked = false;
    if ($checked) return;
    $db->exec("
        CREATE TABLE IF NOT EXISTS `admin_login_otps` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `otp_hash` varchar(255) NOT NULL,
          `expires_at` datetime NOT NULL,
          `attempts` int(11) NOT NULL DEFAULT 0,
          `used` tinyint(1) NOT NULL DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $checked = true;
}

session_start();
?>