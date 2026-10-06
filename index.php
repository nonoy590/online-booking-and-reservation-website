<?php
require_once 'includes/config.php';
$db = getDB();

$ci = isset($_GET['check_in'])  ? clean($_GET['check_in'])  : '';
$co = isset($_GET['check_out']) ? clean($_GET['check_out']) : '';
$gs = isset($_GET['guests'])    ? (int)$_GET['guests']      : 1;

if ($ci && $co) {
    /*
     * AVAILABILITY MUST MATCH booking.php
     * -----------------------------------
     * Use the same reservation rules as the booking page:
     * - overlap = existing_start < requested_end AND existing_end > requested_start
     * - NULL/equal/earlier checkout = one-day reservation
     * - Cancelled and Rejected do not block
     * - Exclusive Resort blocks the whole resort
     */

    $selectedStart = date('Y-m-d', strtotime($ci));
    $selectedEnd   = date('Y-m-d', strtotime($co));

    // Prevent an invalid/same-day range from becoming "available".
    if ($selectedEnd <= $selectedStart) {
        $selectedEnd = date('Y-m-d', strtotime($selectedStart . ' +1 day'));
    }

    // EXACT WHOLE-RESORT Exclusive check used by booking.php.
    // The cottage_id IS NULL + blank booking_type fallback catches old
    // Exclusive records that were saved before the type was standardized.
    $exclusiveStmt = $db->prepare("
        SELECT 1
        FROM reservations er
        WHERE LOWER(TRIM(COALESCE(er.status, ''))) NOT IN ('cancelled', 'rejected')
          AND (
                LOWER(TRIM(COALESCE(er.booking_type, ''))) IN ('exclusive', 'exclusive resort')
                OR (
                    er.cottage_id IS NULL
                    AND LOWER(TRIM(COALESCE(er.booking_type, ''))) = ''
                )
              )
          AND er.check_in < ?
          AND (
                CASE
                    WHEN er.check_out IS NULL OR er.check_out <= er.check_in
                    THEN DATE_ADD(er.check_in, INTERVAL 1 DAY)
                    ELSE er.check_out
                END
              ) > ?
        LIMIT 1
    ");
    $exclusiveStmt->execute([$selectedEnd, $selectedStart]);
    $exclusiveActive = (bool)$exclusiveStmt->fetchColumn();

    // EXACT accommodation overlap used by booking.php, with the same
    // one-day normalization for old/invalid checkout values.
    $stmt = $db->prepare("
        SELECT c.*,
            (
                SELECT COUNT(*)
                FROM reservations r
                WHERE r.cottage_id = c.id
                  AND LOWER(TRIM(COALESCE(r.status, ''))) NOT IN ('cancelled', 'rejected')
                  AND r.check_in < ?
                  AND (
                        CASE
                            WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                            THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                            ELSE r.check_out
                        END
                      ) > ?
            ) AS cottage_booked
        FROM cottages c
        WHERE c.is_available = 1
        ORDER BY
            FIELD(
                c.category,
                'Cottage - Kids Pool',
                'Cottage - Adult Pool',
                'Rooms',
                'Pavilion'
            ),
            c.name ASC
    ");
    $stmt->execute([$selectedEnd, $selectedStart]);

} else {
    // No complete date range selected: show today's availability using
    // exactly the same rules as the selected-date search.
    $selectedStart = date('Y-m-d');
    $selectedEnd   = date('Y-m-d', strtotime('+1 day'));

    $exclusiveStmt = $db->prepare("
        SELECT 1
        FROM reservations er
        WHERE LOWER(TRIM(COALESCE(er.status, ''))) NOT IN ('cancelled', 'rejected')
          AND (
                LOWER(TRIM(COALESCE(er.booking_type, ''))) IN ('exclusive', 'exclusive resort')
                OR (
                    er.cottage_id IS NULL
                    AND LOWER(TRIM(COALESCE(er.booking_type, ''))) = ''
                )
              )
          AND er.check_in < ?
          AND (
                CASE
                    WHEN er.check_out IS NULL OR er.check_out <= er.check_in
                    THEN DATE_ADD(er.check_in, INTERVAL 1 DAY)
                    ELSE er.check_out
                END
              ) > ?
        LIMIT 1
    ");
    $exclusiveStmt->execute([$selectedEnd, $selectedStart]);
    $exclusiveActive = (bool)$exclusiveStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT c.*,
            (
                SELECT COUNT(*)
                FROM reservations r
                WHERE r.cottage_id = c.id
                  AND LOWER(TRIM(COALESCE(r.status, ''))) NOT IN ('cancelled', 'rejected')
                  AND r.check_in < ?
                  AND (
                        CASE
                            WHEN r.check_out IS NULL OR r.check_out <= r.check_in
                            THEN DATE_ADD(r.check_in, INTERVAL 1 DAY)
                            ELSE r.check_out
                        END
                      ) > ?
            ) AS cottage_booked
        FROM cottages c
        WHERE c.is_available = 1
        ORDER BY
            FIELD(
                c.category,
                'Cottage - Kids Pool',
                'Cottage - Adult Pool',
                'Rooms',
                'Pavilion'
            ),
            c.name ASC
    ");
    $stmt->execute([$selectedEnd, $selectedStart]);
}

// Keep one simple field for the existing card UI.
// Exclusive Resort always wins: every accommodation becomes OCCUPIED.
$all_cottages = $stmt->fetchAll();
foreach ($all_cottages as &$c) {
    // Exclusive Resort always wins and occupies every accommodation.
    $c['is_booked_for_dates'] =
        ($exclusiveActive || (int)$c['cottage_booked'] > 0)
        ? 1
        : 0;
}
unset($c);
$grouped = [];
foreach ($all_cottages as $c) {
    $grouped[$c['category']][] = $c;
}

$gallery_photos = $db->query("SELECT * FROM gallery_images ORDER BY category ASC, sort_order ASC, id DESC")->fetchAll();
$gallery_grouped = [];
foreach ($gallery_photos as $g) { $gallery_grouped[$g['category']][] = $g; }

$category_info = [
    'Cottage - Kids Pool' => ['icon' => '🏊‍♀️', 'desc' => 'Cozy cottages near the kids’ pool — perfect for families, allowing parents to easily keep an eye on their children.'],
    'Cottage - Adult Pool' => ['icon' => '🏊', 'desc' => 'Cottages near the adult pool — a quieter spot for couples and grown-up groups.'],
    'Rooms'    => ['icon' => '✨', 'desc' => 'Luxury rooms with split-type aircon, premium beds, and resort-grade amenities.'],
    'Pavilion'  => ['icon' => '🎉', 'desc' => 'Large open-air venues for events — birthdays, weddings, reunions, and parties.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S-Five Inland Resort — Your Tropical Escape</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <link rel="stylesheet" href="css/mobile-app.css?v=<?= filemtime(__DIR__ . '/css/mobile-app.css') ?>">
</head>
<body class="has-bottom-nav">

<nav class="navbar" id="navbar">
    <div class="nav-container">

        <a href="index.php" class="nav-logo">
            <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
            <span class="logo-text">S-Five Inland Resort</span>
        </a>

        <ul class="nav-links">
            <li><a href="#cottages">Cottages</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#amenities">Amenities</a></li>
            <li><a href="#rates-rules">Rates &amp; Rules</a></li>
            <?php if (!empty($gallery_photos)): ?>
            <li><a href="#gallery">Gallery</a></li>
            <?php endif; ?>
            <li><a href="check_booking.php">My Booking</a></li>
            <li><a href="booking.php" class="btn-nav">Book Now</a></li>
        </ul>

        <button class="nav-toggle" id="navToggle" aria-expanded="false">☰</button>
    </div>
</nav>

<?php $active_page = 'home'; require 'includes/drawer.php'; ?>

<section class="hero" id="home">
    <div class="hero-bg">
        <div class="hero-overlay"></div>
    </div>
    <div class="hero-content">
        <p class="hero-tagline">Welcome to</p>
        <h1 class="hero-title">S Five Inland<br><em>Resort</em></h1>
        <p class="hero-sub">Where the breeze whispers and every cottage tells a story.</p>
        <div class="hero-btns">
            <a href="booking.php" class="btn-primary">Reserve Now</a>
            <a href="#cottages" class="btn-ghost">Explore Cottages</a>
        </div>
    </div>
</section>

<section class="availability-bar">
    <div class="avail-container">
        <h3>Check Availability</h3>
        <form action="index.php" method="GET" class="avail-form" id="availForm">
            <div class="avail-field">
                <label>Check-in</label>
                <input type="date" name="check_in" id="check_in" value="<?= htmlspecialchars($ci) ?>"
                       min="<?= date('Y-m-d') ?>">
            </div>
            <div class="avail-divider">→</div>
            <div class="avail-field">
                <label>Check-out</label>
                <input type="date" name="check_out" id="check_out" value="<?= htmlspecialchars($co) ?>"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
            </div>
            <div class="avail-field">
                <label>Guests</label>
                <select name="guests">
                    <?php for($i=1;$i<=60;$i++): ?>
                    <option value="<?=$i?>" <?=$gs==$i?'selected':''?>><?=$i?> <?=$i==1?'Guest':'Guests'?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn-check">Check →</button>
            <?php if ($ci && $co): ?>
            <a href="index.php#cottages" class="btn-clear-avail">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <?php if ($ci && $co): ?>
    <div class="avail-notice">
        Showing availability for <strong><?= date('M d', strtotime($ci)) ?> → <?= date('M d, Y', strtotime($co)) ?></strong>
        &nbsp;·&nbsp; <?= $gs ?> guest(s)
    </div>
    <?php endif; ?>
</section>

<section class="cottages-section" id="cottages">
    <div class="container">
        <div class="section-header">
            <p class="section-label">Accommodations</p>
            <h2 class="section-title">Our <em>Cottages</em></h2>
            <p class="section-desc">Each cottage is designed to give you the full Filipino inland resort experience — rustic, warm, and deeply restful.</p>
        </div>

        <?php foreach ($grouped as $category => $cottages): ?>
        <?php $info = $category_info[$category]; ?>

        <div class="category-header">
            <div class="cat-icon"><?= $info['icon'] ?></div>
            <div class="cat-info">
                <h3><?= $category ?></h3>
                <p><?= $info['desc'] ?></p>
            </div>
            <div class="cat-count"><?= count(array_filter($cottages, fn($x) => !(bool)$x['is_booked_for_dates'])) ?> available</div>
        </div>

        <div class="cottages-grid">
            <?php foreach ($cottages as $c):
                $booked   = (bool)$c['is_booked_for_dates'];
                $type_map = ['Cottage - Kids Pool'=>'cottage_kids_pool','Cottage - Adult Pool'=>'cottage_adult_pool','Rooms'=>'rooms','Pavilion'=>'pavilion'];
                $img_type = $type_map[$c['category']] ?? 'cottage_kids_pool';

                $photo_stmt = $db->prepare("SELECT filename FROM cottage_images WHERE cottage_id=? ORDER BY sort_order ASC LIMIT 1");
                $photo_stmt->execute([$c['id']]);
                $photo_row = $photo_stmt->fetch();
                $thumb_url = $photo_row
                    ? "uploads/cottages/" . $photo_row['filename']
                    : "images/cottage_placeholder.php?type={$img_type}&name=" . urlencode($c['name']) . "&n={$c['id']}";
            ?>
            <div class="cottage-card">

                <a href="cottage.php?id=<?= $c['id'] ?>" class="cottage-img-link">
                    <div class="cottage-img">
                        <img src="<?= $thumb_url ?>"
                             alt="<?= htmlspecialchars($c['name']) ?>"
                             class="cottage-thumb-img">

                        <?php if ($booked): ?>
                        <div class="avail-badge occupied" style="background:#b42318;color:#fff;">
                            <span class="avail-dot"></span> Occupied
                        </div>
                        <?php else: ?>
                        <div class="avail-badge available">
                            <span class="avail-dot"></span> Available
                        </div>
                        <?php endif; ?>

                        <span class="cottage-badge">Up to <?= $c['capacity'] ?> guests</span>
                    </div>
                </a>

                <div class="cottage-body">
                    <div class="cottage-category-tag"><?= $c['category'] ?></div>
                    <h3 class="cottage-name">
                        <a href="cottage.php?id=<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                    </h3>
                    <p class="cottage-desc"><?= htmlspecialchars($c['description']) ?></p>

                    <div class="cottage-amenities">
                        <?php foreach (array_slice(explode(',', $c['amenities']), 0, 3) as $a): ?>
                        <span class="tag"><?= trim($a) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="cottage-footer">
                        <?php if ($c['category'] === 'Pavilion'): ?>
                        <div class="cottage-price is-event">
                            <span class="price-event-label">Priced per occasion</span>
                        </div>
                        <?php elseif ($c['category'] === OVERNIGHT_CATEGORY): ?>
                        <div class="cottage-price">
                            <span class="price-amount">₱<?= number_format($c['price_per_night'], 0) ?></span>
                            <span class="price-unit">/day or night</span>
                        </div>
                        <?php else: ?>
                        <div class="cottage-price">
                            <span class="price-amount">₱<?= number_format($c['price_per_night'], 0) ?></span>
                            <span class="price-unit">/day</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($booked): ?>
                        <span class="btn-book" style="opacity:.55;cursor:not-allowed;pointer-events:none;">Occupied for Selected Dates</span>
                        <?php else: ?>
                        <a href="booking.php?cottage_id=<?= $c['id'] ?><?= $ci?"&check_in=$ci":'' ?><?= $co?"&check_out=$co":'' ?><?= $gs?"&guests=$gs":'' ?>"
                           class="btn-book"><?= $c['category'] === 'Pavilion' ? 'Book This Event' : 'Book This' ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endforeach; ?>

        <?php if (empty($all_cottages)): ?>
        <div class="empty-cottages">
            <div>😔</div>
            <p>No cottages found. Please try different dates or guest count.</p>
            <a href="index.php#cottages">Reset</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="about-section" id="about">
    <div class="container about-grid">
        <div class="about-visual">
            <div class="about-img-box"><div class="about-emoji-main">🌴</div>
                <div class="about-badge-float">Est. 2018</div>
            </div>
            <div class="about-stat-cards">
                <div class="stat-card"><strong>11</strong><span>Cottages</span></div>
                <div class="stat-card"><strong>500+</strong><span>Happy Guests</span></div>
                <div class="stat-card"><strong>★ 4.9</strong><span>Rating</span></div>
            </div>
        </div>
        <div class="about-text">
            <p class="section-label">Our Story</p>
            <h2 class="section-title">A Place to <em>Relax, and Create Memories</em></h2>
            <p>Nestled in the peaceful municipality of Barangay San Jose, San Miguel, Iloilo, <strong>S-Five Inland Resort</strong> offers a refreshing escape where families, friends, and visitors can unwind away from the busy pace of everyday life. Surrounded by the calm atmosphere of the countryside, the resort provides a welcoming destination for relaxation, celebrations, and quality time together.</p>
            <p>With its swimming pools, cottages, comfortable rooms, and event spaces, S-Five Inland Resort is an ideal venue for family outings, reunions, birthdays, company gatherings, and overnight stays. Every visit is designed to provide comfort, convenience, and memorable experiences for guests of all ages.</p>
            <a href="booking.php" class="btn-primary" style="margin-top:1.5rem;display:inline-block;">Plan Your Stay</a>
        </div>
    </div>
</section>

<section class="amenities-section" id="amenities">
    <div class="container">
        <div class="section-header">
            <p class="section-label">What We Offer</p>
            <h2 class="section-title">Resort <em>Amenities</em></h2>
        </div>
        <div class="amenities-grid">
            <div class="amenity-item"><div class="amenity-icon">🏊</div><h4>Natural Pools</h4><p>Cool, clean resort pools surrounded by tropical greenery — free for all guests to enjoy.</p></div>
            <div class="amenity-item"><div class="amenity-icon">🏪</div><h4>Store</h4><p>A convenient on-site store for your essential needs and snacks or drinks.</p></div>
            <div class="amenity-item"><div class="amenity-icon">🎉</div><h4>Event Hosting</h4><p>Birthday, wedding, debut, and reunion packages available</p></div>
            <div class="amenity-item"><div class="amenity-icon">🔥</div><h4>BBQ Grilling</h4><p>Outdoor grilling areas for your evening gatherings</p></div>
            <div class="amenity-item"><div class="amenity-icon">⛹️</div><h4>Basketball Court</h4><p>Enjoy a fun and active game at our basketball court, perfect for family and friends.</p></div>
            <div class="amenity-item"><div class="amenity-icon">🅿️</div><h4>Free Parking</h4><p>Safe, spacious parking for all resort guests</p></div>
            <div class="amenity-item"><div class="amenity-icon">🎤</div><h4>Rental Karaoke</h4><p>Enjoy your favorite songs with our videoke rental—perfect for fun moments with family and friends!</p></div>

        </div>
    </div>
</section>


<section class="rates-rules-section" id="rates-rules">
    <div class="container">
        <div class="section-header">
            <p class="section-label">Plan Your Visit</p>
            <h2 class="section-title">Rates <em>&amp; Pool Rules</em></h2>
        </div>

        <div class="rates-rules-grid">
            <div class="poster-card">
                <button type="button" class="poster-item" data-full="images/rates_poster.jpg" data-caption="Rates &amp; Fees">
                    <img src="images/rates_poster.jpg" alt="S-Five Inland Resort rates and fees">
                    <span class="poster-zoom-hint">🔍 Tap to enlarge</span>
                </button>
            </div>
            <div class="poster-card">
                <button type="button" class="poster-item" data-full="images/pool_rules_poster.jpg" data-caption="Pool Rules &amp; Regulations">
                    <img src="images/pool_rules_poster.jpg" alt="S-Five Inland Resort pool rules and regulations">
                    <span class="poster-zoom-hint">🔍 Tap to enlarge</span>
                </button>
            </div>
        </div>
    </div>
</section>

<div class="lightbox" id="postersLightbox">
    <button type="button" class="lightbox-close" id="postersLightboxClose" aria-label="Close">✕</button>
    <button type="button" class="lightbox-nav lightbox-prev" id="postersLightboxPrev" aria-label="Previous">‹</button>
    <img src="" alt="" class="lightbox-img" id="postersLightboxImg">
    <button type="button" class="lightbox-nav lightbox-next" id="postersLightboxNext" aria-label="Next">›</button>
    <div class="lightbox-caption" id="postersLightboxCaption"></div>
</div>


<?php if (!empty($gallery_photos)): ?>
<section class="gallery-section" id="gallery">
    <div class="container">
        <div class="section-header">
            <p class="section-label">See It For Yourself</p>
            <h2 class="section-title">Resort <em>Gallery</em></h2>
            <p class="section-desc">A closer look at S-Five — our pools, gardens, cottages, and the moments guests have shared here.</p>
        </div>

        <div class="gallery-filters">
            <button class="gallery-filter-btn active" data-filter="all">All</button>
            <?php foreach (array_keys($gallery_grouped) as $cat): ?>
            <button class="gallery-filter-btn" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="gallery-grid">
            <?php foreach ($gallery_photos as $g): $photo_name = $g['caption'] ?: $g['category']; ?>
            <button type="button" class="gallery-item" data-category="<?= htmlspecialchars($g['category']) ?>"
                    data-full="uploads/gallery/<?= htmlspecialchars($g['filename']) ?>"
                    data-caption="<?= htmlspecialchars($photo_name) ?>">
                <img src="uploads/gallery/<?= htmlspecialchars($g['filename']) ?>"
                     alt="<?= htmlspecialchars($photo_name . ' — S-Five Inland Resort') ?>" loading="lazy">
                <span class="gallery-item-overlay">
                    <span class="gallery-item-cat"><?= htmlspecialchars($photo_name) ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="lightbox" id="galleryLightbox">
    <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Close">✕</button>
    <button type="button" class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Previous">‹</button>
    <img src="" alt="" class="lightbox-img" id="lightboxImg">
    <button type="button" class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Next">›</button>
    <div class="lightbox-caption" id="lightboxCaption"></div>
</div>
<?php endif; ?>

<footer class="footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
            <p>Your tropical inland escape, anytime.</p>
        </div>
        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#cottages">Cottages</a></li>
                <li><a href="booking.php">Book Now</a></li>
                <li><a href="check_booking.php">Check Reservation</a></li>
            </ul>
        </div>
        <div class="footer-contact">
            <h4>Contact</h4>
            <p>📍 San Jose, San Miguel Iloilo</p>
            <p>📞 09063035392 or 09393042464 </p>
            <p>✉️ sfiveinlandresort2018@gmail.com</p>
            <p>💚 GCash:09393042464 </p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> S-Five Inland Resort. All rights reserved.</p>
    </div>
</footer>

<div class="s5-contact-footer-card">
    <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
    <h3>S-Five Inland Resort</h3>
    <p class="tagline">Relax. Refresh. Reconnect.</p>
    <p class="s5-copyright">&copy; <?= date('Y') ?> S-Five Inland Resort. All rights reserved.</p>
</div>

<?php require 'includes/bottom_nav.php'; ?>

<script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
</body>
</html>