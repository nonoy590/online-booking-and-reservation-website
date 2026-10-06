<?php

if (!function_exists('gcash_log')) {
    function gcash_log(string $message): void
    {
        $root = dirname(__DIR__);
        $dir = $root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'gcash_upload.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}

function getGcashSettings(PDO $db): array
{
    $defaults = [
        'account_name' => '',
        'account_number' => '',
        'qr_image' => ''
    ];

    // Keep compatibility with the existing project table if it exists.
    foreach (['gcash_settings', 'settings'] as $table) {
        try {
            $stmt = $db->query("SELECT * FROM `{$table}` LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                foreach ($defaults as $key => $value) {
                    if (array_key_exists($key, $row)) {
                        $defaults[$key] = (string)$row[$key];
                    }
                }
                return $defaults;
            }
        } catch (Throwable $e) {
        }
    }

    return $defaults;
}

function uploadGcashProof(array $file, string $bookingCode): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        gcash_log('Upload rejected for ' . $bookingCode . ': error=' . ($file['error'] ?? 'missing'));
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        gcash_log('Upload rejected for ' . $bookingCode . ': tmp file is not a valid HTTP upload');
        return null;
    }

    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        gcash_log('Upload rejected for ' . $bookingCode . ': invalid size=' . $size);
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    $mime = function_exists('mime_content_type') ? @mime_content_type($tmp) : '';
    if (!isset($allowed[$mime])) {
        gcash_log('Upload rejected for ' . $bookingCode . ': mime=' . $mime);
        return null;
    }

    $root = dirname(__DIR__);
    $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'gcash';

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        gcash_log('Could not create upload directory: ' . $uploadDir);
        return null;
    }

    if (!is_writable($uploadDir)) {
        gcash_log('Upload directory is not writable: ' . $uploadDir);
        return null;
    }

    $code = preg_replace('/[^A-Za-z0-9]/', '', $bookingCode);
    if ($code === '') {
        $code = 'UNKNOWN';
    }

    // Filename is unique but always contains the booking code so the admin
    // page can recover old uploads even if proof_image is blank.
    $filename = 'gcash_' . $code . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!@move_uploaded_file($tmp, $destination)) {
        gcash_log('move_uploaded_file failed for ' . $bookingCode . ' -> ' . $destination);
        return null;
    }

    @chmod($destination, 0644);
    gcash_log('Screenshot saved: booking=' . $bookingCode . ' file=' . $filename . ' path=' . $destination);

    return $filename;
}

function saveGcashSubmission(array $data): array
{
    $db = $data['db'] ?? null;
    if (!$db instanceof PDO) {
        return ['success' => false, 'error' => 'Database connection is missing.'];
    }

    $reservationId = (int)($data['reservation_id'] ?? 0);
    if ($reservationId <= 0) {
        return ['success' => false, 'error' => 'Invalid reservation id.'];
    }

    $reference = trim((string)($data['reference_number'] ?? ''));
    $amount = (float)($data['amount'] ?? 0);
    $senderName = trim((string)($data['guest_name'] ?? $data['sender_name'] ?? ''));
    $senderPhone = trim((string)($data['guest_phone'] ?? $data['sender_phone'] ?? ''));
    $proof = basename(str_replace('\\', '/', (string)($data['proof_image'] ?? '')));

    if ($proof === '') {
        return ['success' => false, 'error' => 'Proof filename is empty.'];
    }

    try {
        // Confirm the reservation exists before creating the payment row.
        $check = $db->prepare("SELECT id FROM reservations WHERE id=? LIMIT 1");
        $check->execute([$reservationId]);
        if (!$check->fetchColumn()) {
            return ['success' => false, 'error' => 'Reservation was not found.'];
        }

        // A guest re-submitting the SAME kind of payment after a rejection (or
        // before verification) updates that row. A verified payment is never
        // overwritten, and a Balance payment always gets its own row.
        $paymentType = in_array(($data['payment_type'] ?? 'Full'), ['Full','Downpayment','Balance'], true)
            ? $data['payment_type'] : 'Full';

        $existing = $db->prepare("SELECT id FROM gcash_payments
            WHERE reservation_id=? AND payment_type=? AND status IN ('Pending','Rejected')
            ORDER BY id DESC LIMIT 1");
        $existing->execute([$reservationId, $paymentType]);
        $paymentId = (int)$existing->fetchColumn();

        if ($paymentId > 0) {
            $stmt = $db->prepare("UPDATE gcash_payments
                SET reference_number=?, amount=?, sender_name=?, sender_number=?, proof_image=?,
                    status='Pending', notes=NULL, submitted_at=NOW()
                WHERE id=?");
            $stmt->execute([$reference, $amount, $senderName, $senderPhone, $proof, $paymentId]);
        } else {
            $stmt = $db->prepare("INSERT INTO gcash_payments
                (reservation_id, reference_number, amount, sender_name, sender_number,
                 proof_image, status, notes, submitted_at, payment_type)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', NULL, NOW(), ?)");
            $stmt->execute([
                $reservationId,
                $reference,
                $amount,
                $senderName,
                $senderPhone,
                $proof,
                $paymentType
            ]);
            $paymentId = (int)$db->lastInsertId();
        }

        // Payment status is derived from verified payments (a pending Balance
        // proof must not flip a Partially Paid booking back to Pending).
        syncReservationPayment($db, $reservationId);

        gcash_log('Payment row saved: reservation=' . $reservationId . ' payment=' . $paymentId . ' proof=' . $proof);

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'proof_image' => $proof
        ];
    } catch (Throwable $e) {
        gcash_log('Payment row save failed for reservation ' . $reservationId . ': ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
