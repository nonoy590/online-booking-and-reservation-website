<?php
require_once 'auth.php';

if (!isMainAdmin($admin_email)) {
    http_response_code(403);
    include 'partials/header.php';
    echo '<div class="alert-error">Only the main admin can manage admin accounts.</div>';
    include 'partials/footer.php';
    exit;
}

$page_title = 'Admin Accounts';
$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = clean($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $msg = "error:All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "error:Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $msg = "error:Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $msg = "error:Password and confirmation do not match.";
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $msg = "error:That email is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")
               ->execute([$name, $email, $hashed]);
            $msg = "success:Admin account for " . $email . " created. They can now sign in at login.php — the login code will be emailed to that address.";
        }
    }
}

$admins = $db->query("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at ASC")->fetchAll();

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id === (int)$_SESSION['admin_id']) {
        $msg = 'error:You can\'t delete the account you\'re currently logged in as.';
    } elseif (count($admins) <= 1) {
        $msg = 'error:Can\'t delete the last admin account — you\'d lock everyone out.';
    } else {
        $db->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$id]);
        $msg = 'success:Admin account deleted.';
        $admins = $db->query("SELECT id, name, email, created_at FROM users WHERE role = 'admin' ORDER BY created_at ASC")->fetchAll();
    }
}

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
include 'partials/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Accounts — S-Five Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<?php if ($msg_text): ?>
<div class="alert-<?= $msg_type ?>"><?= htmlspecialchars($msg_text) ?></div>
<?php endif; ?>

<div class="card" style="max-width:520px;">
    <div class="card-header">
        <h3>Create Admin Account</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter your name...." required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter you gmail..." required>
                <small style="display:block;color:#888;font-size:0.78rem;margin-top:0.3rem;">Login codes are emailed here, so use a real, working inbox.</small>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6" autocomplete="new-password">
            </div>
            <button type="submit" class="btn-save">Create Admin</button>
        </form>
    </div>
</div>

<div class="card" style="max-width:720px;margin-top:1.5rem;">
    <div class="card-header">
        <h3>All Admins <span class="count-badge"><?= count($admins) ?></span></h3>
    </div>
    <div class="card-body">
        <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                <tr><td colspan="4" class="empty-row">No admin accounts found.</td></tr>
                <?php endif; ?>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <?php if ((int)$a['id'] === (int)$_SESSION['admin_id']): ?>
                        <span style="color:#aaa;font-size:0.85rem;">Current user</span>
                        <?php else: ?>
                        <a href="admins.php?delete=<?= $a['id'] ?>"
                           onclick="return confirm('Delete admin account <?= htmlspecialchars($a['email']) ?>? This cannot be undone.')">
                            🗑️ Delete
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>