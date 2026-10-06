<?php
// admin/login.php — two-step admin login: email+password, then an emailed
// one-time code. The session only gets admin_id (i.e. "logged in") once
// the OTP step succeeds — see includes/admin_otp.php.
require_once '../includes/config.php';
require_once '../includes/mailer.php';
require_once '../includes/admin_otp.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$db    = getDB();
$error = '';
$info  = '';

if (!empty($_SESSION['otp_send_failed'])) {
    $error = 'Could not send the login code to your email. Check logs/mailer.log for the reason (bad API key, unverified sender, or a network/curl error), then use Resend once fixed.';
    unset($_SESSION['otp_send_failed']);
}

$pendingUid   = $_SESSION['otp_pending_uid']   ?? null;
$pendingEmail = $_SESSION['otp_pending_email'] ?? null;
$pendingName  = $_SESSION['otp_pending_name']  ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stage = $_POST['stage'] ?? '';

    if ($stage === 'credentials') {
        $email    = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['otp_pending_uid']   = $user['id'];
            $_SESSION['otp_pending_email'] = $user['email'];
            $_SESSION['otp_pending_name']  = $user['name'];
            $_SESSION['otp_sent_at']       = time();

            generateAndSendAdminOtp($db, $user['id'], $user['email'], $user['name'])
                or $_SESSION['otp_send_failed'] = true;

            // PRG: redirect so a page refresh doesn't resubmit the password
            // or re-trigger credential checking.
            header('Location: login.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }

    elseif ($stage === 'otp' && $pendingUid) {
        $code   = trim($_POST['otp'] ?? '');
        $result = verifyAdminOtp($db, $pendingUid, $code);

        if ($result === 'ok') {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$pendingUid]);
            $user = $stmt->fetch();

            unset($_SESSION['otp_pending_uid'], $_SESSION['otp_pending_email'], $_SESSION['otp_pending_name'], $_SESSION['otp_sent_at']);
            session_regenerate_id(true);

            $_SESSION['admin_id']    = $user['id'];
            $_SESSION['admin_name']  = $user['name'];
            $_SESSION['admin_email'] = $user['email'];
            header('Location: index.php');
            exit;
        } elseif ($result === 'expired') {
            $error = 'That code expired. Request a new one below.';
        } elseif ($result === 'locked') {
            $error = 'Too many wrong attempts. Request a new code below.';
        } elseif ($result === 'none') {
            unset($_SESSION['otp_pending_uid'], $_SESSION['otp_pending_email'], $_SESSION['otp_pending_name'], $_SESSION['otp_sent_at']);
            header('Location: login.php');
            exit;
        } else {
            $error = 'Incorrect code. Please try again.';
        }
    }

    elseif ($stage === 'resend' && $pendingUid) {
        $elapsed = time() - (int)($_SESSION['otp_sent_at'] ?? 0);
        if ($elapsed < ADMIN_OTP_RESEND_COOLDOWN_SECONDS) {
            $error = 'Please wait ' . (ADMIN_OTP_RESEND_COOLDOWN_SECONDS - $elapsed) . 's before requesting another code.';
        } else {
            $sent = generateAndSendAdminOtp($db, $pendingUid, $pendingEmail, $pendingName);
            $_SESSION['otp_sent_at'] = time();
            if ($sent) {
                $info = 'A new code has been sent to your email.';
            } else {
                $error = 'Could not send the code. Check logs/mailer.log for the reason.';
            }
        }
    }

    elseif ($stage === 'cancel') {
        unset($_SESSION['otp_pending_uid'], $_SESSION['otp_pending_email'], $_SESSION['otp_pending_name'], $_SESSION['otp_sent_at']);
        header('Location: login.php');
        exit;
    }

    $pendingUid   = $_SESSION['otp_pending_uid']   ?? null;
    $pendingEmail = $_SESSION['otp_pending_email'] ?? null;
    $pendingName  = $_SESSION['otp_pending_name']  ?? null;
}

$awaitingOtp = !empty($pendingUid);

function maskEmail($email) {
    if (strpos($email, '@') === false) return $email;
    [$local, $domain] = explode('@', $email, 2);
    $visible = min(2, strlen($local));
    return substr($local, 0, $visible) . str_repeat('*', max(1, strlen($local) - $visible)) . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — S-Five Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-logo">
                <span>👤</span>
                <h1>S-Five Resort</h1>
                <p>Admin Panel</p>
            </div>

            <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($info): ?>
            <div class="alert-success"><?= htmlspecialchars($info) ?></div>
            <?php endif; ?>

            <?php if (!$awaitingOtp): ?>
            <form method="POST" class="login-form">
                <input type="hidden" name="stage" value="credentials">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@gmail.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-login">Sign In →</button>
            </form>

            <?php else: ?>
            <p style="font-size:0.85rem;color:var(--text-mid,#4a4a4a);margin:-0.5rem 0 1.1rem;">
                We sent a 6-digit code to <strong><?= htmlspecialchars(maskEmail($pendingEmail)) ?></strong>.
                It expires in <?= ADMIN_OTP_TTL_MINUTES ?> minutes.
            </p>
            <form method="POST" class="login-form">
                <input type="hidden" name="stage" value="otp">
                <div class="form-group">
                    <label>6-Digit Code</label>
                    <input type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           placeholder="123456" autocomplete="one-time-code" autofocus required
                           style="letter-spacing:0.4em;text-align:center;font-size:1.2rem;">
                </div>
                <button type="submit" class="btn-login">Verify →</button>
            </form>
            <form method="POST" style="margin-top:0.9rem;display:flex;justify-content:space-between;">
                <input type="hidden" name="stage" value="resend">
                <button type="submit" style="background:none;border:none;color:var(--green-deep,#1a3a2e);font-size:0.82rem;font-weight:600;cursor:pointer;padding:0;">Resend code</button>
            </form>
            <form method="POST" style="margin-top:0.4rem;">
                <input type="hidden" name="stage" value="cancel">
                <button type="submit" style="background:none;border:none;color:var(--text-light,#888);font-size:0.82rem;cursor:pointer;padding:0;">← Use a different account</button>
            </form>
            <?php endif; ?>

            <a href="../index.php" class="back-link">← Back to Resort Website</a>
        </div>
    </div>
</body>
</html>