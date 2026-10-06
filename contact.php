<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — S-Five Inland Resort</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <link rel="stylesheet" href="css/mobile-app.css?v=<?= filemtime(__DIR__ . '/css/mobile-app.css') ?>">
</head>
<body>

<nav class="navbar navbar-light navbar-sub" id="navbar">
    <div class="nav-container">
        <a href="index.php" class="s5-back" aria-label="Back home">←</a>
        <span class="s5-header-title">Contact Us</span>
        <a href="index.php" class="nav-logo">
            <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
            <span class="logo-text">S-Five Inland Resort</span>
        </a>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="booking.php" class="btn-nav">Book Now</a></li>
        </ul>
        <button class="nav-toggle" id="navToggle" aria-expanded="false">☰</button>
    </div>
</nav>

<?php $active_page = 'contact'; require 'includes/drawer.php'; ?>

<div class="s5-contact-page">

    <div class="s5-contact-card">
        <a href="tel:09063035392" class="s5-contact-row">
            <span class="s5-contact-icon">📞</span>
            <span class="s5-contact-main"><strong>0906 303 5392</strong><span>Call anytime</span></span>
        </a>
        <a href="mailto:sfiveinlandresort2018@gmail.com" class="s5-contact-row">
            <span class="s5-contact-icon">✉️</span>
            <span class="s5-contact-main"><strong>sfiveinlandresort2018@gmail.com</strong><span>Send us an email</span></span>
        </a>
        <a href="https://maps.google.com/?q=Barangay+San+Jose,+San+Miguel,+Iloilo" target="_blank" class="s5-contact-row">
            <span class="s5-contact-icon">📍</span>
            <span class="s5-contact-main"><strong>San Jose, San Miguel, Iloilo</strong><span>Visit us</span></span>
        </a>
    </div>

    <h4 style="font-family:'Jost',sans-serif;font-weight:600;font-size:0.85rem;color:var(--text-mid);margin:0 0 0.7rem 0.3rem;">Follow Us</h4>
    <div class="s5-social-row">
        <a href="#" aria-label="Facebook">📘</a>
        <a href="#" aria-label="Instagram">📷</a>
        <a href="#" aria-label="TikTok">🎵</a>
        <a href="#" aria-label="Messenger">💬</a>
    </div>

    <div class="s5-contact-footer-card">
        <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
        <h3>S-Five Inland Resort</h3>
        <p class="tagline">Relax. Refresh. Reconnect.</p>
        <p class="s5-copyright">&copy; <?= date('Y') ?> S-Five Inland Resort. All rights reserved.</p>
    </div>
</div>

<script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
</body>
</html>
