<?php
if (!isset($active_page)) $active_page = '';
$home_anchor = ($active_page === 'home') ? '' : 'index.php';
?>
<nav class="s5-bottom-nav" aria-label="Primary">
    <a href="index.php" class="s5-bn-item <?= $active_page==='home'?'is-active':'' ?>" data-section="home">
        <span class="s5-bn-icon">🏠</span><span class="s5-bn-label">Home</span>
    </a>
    <a href="<?= $home_anchor ?>#cottages" class="s5-bn-item <?= $active_page==='cottages'?'is-active':'' ?>" data-section="cottages">
        <span class="s5-bn-icon">🏕️</span><span class="s5-bn-label">Cottages</span>
    </a>
    <a href="<?= $home_anchor ?>#amenities" class="s5-bn-item <?= $active_page==='amenities'?'is-active':'' ?>" data-section="amenities">
        <span class="s5-bn-icon">🏊</span><span class="s5-bn-label">Amenities</span>
    </a>
    <a href="<?= $home_anchor ?>#gallery" class="s5-bn-item <?= $active_page==='gallery'?'is-active':'' ?>" data-section="gallery">
        <span class="s5-bn-icon">🖼️</span><span class="s5-bn-label">Gallery</span>
    </a>
    <a href="check_booking.php" class="s5-bn-item <?= $active_page==='my_booking'?'is-active':'' ?>" data-page="my_booking">
        <span class="s5-bn-icon">📅</span><span class="s5-bn-label">My Booking</span>
    </a>
</nav>
