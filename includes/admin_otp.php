<?php
const ADMIN_OTP_TTL_MINUTES  = 10;
const ADMIN_OTP_MAX_ATTEMPTS = 5;
const ADMIN_OTP_RESEND_COOLDOWN_SECONDS = 60;

// Generates a new 6-digit code for $userId, stores its hash, emails it to
// $toEmail, and returns true on success (email actually sent) or false.
function generateAndSendAdminOtp($db, $userId, $toEmail, $toName) {
    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash    = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + ADMIN_OTP_TTL_MINUTES * 60);

    $db->prepare("INSERT INTO admin_login_otps (user_id, otp_hash, expires_at) VALUES (?, ?, ?)")
       ->execute([$userId, $hash, $expires]);

    $subject = 'Your admin login code — ' . SITE_NAME;
    $html = '
    <div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#1c1c1c;">
        <h2 style="color:#1a3a2e;margin-bottom:0.25rem;">Admin Login Code</h2>
        <p>Hi ' . htmlspecialchars($toName) . ',</p>
        <p>Use this code to finish signing in to the ' . htmlspecialchars(SITE_NAME) . ' admin panel. It expires in ' . ADMIN_OTP_TTL_MINUTES . ' minutes.</p>
        <p style="font-size:2rem;font-weight:700;letter-spacing:0.35em;text-align:center;background:#f3f8f4;border-radius:8px;padding:0.9rem;margin:1.2rem 0;color:#1a3a2e;">' . $code . '</p>
        <p style="font-size:0.85rem;color:#888;">If you didn\'t try to log in, you can ignore this email — your account is still safe.</p>
    </div>';

    return sendBrevoEmail($toEmail, $toName, $subject, $html);
}

// Checks $code against the most recent unused, unexpired OTP for $userId.
function verifyAdminOtp($db, $userId, $code) {
    $stmt = $db->prepare("SELECT * FROM admin_login_otps WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) return 'none';

    if (strtotime($row['expires_at']) < time()) {
        return 'expired';
    }
    if ($row['attempts'] >= ADMIN_OTP_MAX_ATTEMPTS) {
        return 'locked';
    }

    $db->prepare("UPDATE admin_login_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);

    if (!password_verify($code, $row['otp_hash'])) {
        return 'invalid';
    }

    $db->prepare("UPDATE admin_login_otps SET used = 1 WHERE id = ?")->execute([$row['id']]);
    return 'ok';
}
?>