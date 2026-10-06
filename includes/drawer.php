<?php
if (!isset($active_page)) $active_page = '';
$home_anchor = ($active_page === 'home') ? '' : 'index.php';
?>
<div class="s5-drawer" id="s5Drawer" aria-hidden="true">
    <div class="s5-drawer-header">
        <a href="index.php" class="nav-logo">
            <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
            <span class="logo-text">S-Five Inland Resort</span>
        </a>
        <button type="button" class="s5-drawer-close" id="s5DrawerClose" aria-label="Close menu">✕</button>
    </div>
    <ul class="s5-drawer-links">
        <li><a href="index.php" class="<?= $active_page==='home'?'is-active':'' ?>"><span class="s5-dl-icon">🏠</span> Home</a></li>
        <li><a href="<?= $home_anchor ?>#cottages" class="<?= $active_page==='cottages'?'is-active':'' ?>"><span class="s5-dl-icon">🏕️</span> Cottages</a></li>
        <li><a href="<?= $home_anchor ?>#amenities"><span class="s5-dl-icon">🏊</span> Amenities</a></li>
        <li><a href="<?= $home_anchor ?>#rates-rules"><span class="s5-dl-icon">📋</span> Rates &amp; Rules</a></li>
        <li><a href="<?= $home_anchor ?>#gallery"><span class="s5-dl-icon">🖼️</span> Gallery</a></li>
        <li><a href="check_booking.php" class="<?= $active_page==='my_booking'?'is-active':'' ?>"><span class="s5-dl-icon">📅</span> My Booking</a></li>
        <li><a href="<?= $home_anchor ?>#about"><span class="s5-dl-icon">ℹ️</span> About Us</a></li>
        <li><a href="contact.php" class="<?= $active_page==='contact'?'is-active':'' ?>"><span class="s5-dl-icon">📞</span> Contact Us</a></li>
    </ul>
    <div class="s5-drawer-footer">
        <a href="booking.php" class="s5-drawer-book">📅 Book Now</a>
    </div>
</div>
<div class="s5-drawer-backdrop" id="s5DrawerBackdrop"></div>