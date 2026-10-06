<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin' ?> — S-Five Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <span>👤</span>
        <div>
            <strong>S-Five Resort</strong>
            <small>Admin Panel</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php"        class="nav-item <?= $current==='index.php'        ?'active':'' ?>"><span class="ni">📊</span> Dashboard</a>
        <a href="reservations.php" class="nav-item <?= $current==='reservations.php' ?'active':'' ?>"><span class="ni">📋</span> Reservations</a>
        <a href="cottages.php"     class="nav-item <?= $current==='cottages.php'     ?'active':'' ?>"><span class="ni">🏡</span> Cottages</a>
        <a href="gallery.php"      class="nav-item <?= $current==='gallery.php'      ?'active':'' ?>"><span class="ni">🖼️</span> Gallery</a>
        <a href="guests.php"       class="nav-item <?= $current==='guests.php'       ?'active':'' ?>"><span class="ni">👥</span> Guests</a>
        <a href="reports.php"      class="nav-item <?= $current==='reports.php'      ?'active':'' ?>"><span class="ni">📈</span> Reports</a>
        <a href="gcash.php"        class="nav-item <?= $current==='gcash.php'        ?'active':'' ?>"><span class="ni">💚</span> GCash Payments</a>
        <a href="settings.php"     class="nav-item <?= $current==='settings.php'     ?'active':'' ?>"><span class="ni">⚙️</span> Settings</a>
        <?php if (isMainAdmin($admin_email ?? '')): ?>
        <a href="admins.php"       class="nav-item <?= $current==='admins.php'       ?'active':'' ?>"><span class="ni">🛡️</span> Admin Accounts</a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="settings.php" class="admin-user" style="text-decoration:none;color:inherit;">
            <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
            <div>
                <strong><?= htmlspecialchars($admin_name) ?></strong>
                <small>Administrator</small>
            </div>
        </a>
        <a href="logout.php" class="btn-logout">Sign Out</a>
        <a href="../index.php" class="view-site" target="_blank">↗ View Site</a>
    </div>
</aside>

<main class="admin-main">
<div class="admin-topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">☰</button>
        <h2 class="page-title"><?= $page_title ?? 'Dashboard' ?></h2>
    </div>
    <div class="topbar-right">
        <span class="topbar-date"><?= date('l, F d Y') ?></span>
    </div>
</div>
<div class="admin-content">