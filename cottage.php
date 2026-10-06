<?php
require_once 'includes/config.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT * FROM cottages WHERE id = ? AND is_available = 1");
$stmt->execute([$id]);
$cottage = $stmt->fetch();
if (!$cottage) { header('Location: index.php'); exit; }

$booked_stmt = $db->prepare("
    SELECT COUNT(*) FROM reservations
    WHERE cottage_id=? AND status IN ('Confirmed','Pending')
    AND check_in <= CURDATE() AND check_out > CURDATE()
");
$booked_stmt->execute([$id]);
$is_booked_now = $booked_stmt->fetchColumn() > 0;

$related = $db->prepare("SELECT * FROM cottages WHERE category=? AND id!=? AND is_available=1 LIMIT 3");
$related->execute([$cottage['category'], $id]);
$related = $related->fetchAll();

$type_map = ['Cottage - Kids Pool'=>'cottage_kids_pool','Cottage - Adult Pool'=>'cottage_adult_pool','Rooms'=>'rooms','Pavilion'=>'pavilion'];
$img_type = $type_map[$cottage['category']] ?? 'cottage_kids_pool';

$photo_stmt = $db->prepare("SELECT filename FROM cottage_images WHERE cottage_id=? ORDER BY sort_order ASC LIMIT 1");
$photo_stmt->execute([$id]);
$photo_row  = $photo_stmt->fetch();
$single_img = $photo_row
    ? "uploads/cottages/" . $photo_row['filename']
    : "images/cottage_placeholder.php?type={$img_type}&name=" . urlencode($cottage['name']) . "&n={$id}";

$amenities_list = array_map('trim', explode(',', $cottage['amenities']));

// Pavilions are priced per event, not per night — pull their event prices.
$is_pavilion = ($cottage['category'] === 'Pavilion');
$event_prices = $is_pavilion ? getPavilionEventPrices($db, $id) : [];
// Only Rooms can be booked Overnight — every other category is Day Use only.
$is_overnight_eligible = ($cottage['category'] === OVERNIGHT_CATEGORY);

$event_info = [
    'Party Event'    => ['icon' => '🎉', 'desc' => 'General parties, reunions, and get-togethers.'],
    'Birthday Event' => ['icon' => '🎂', 'desc' => 'Birthday celebrations of any age.'],
    'Marriage Event' => ['icon' => '💍', 'desc' => 'Weddings and wedding-related celebrations.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cottage['name']) ?> — S-Five Inland Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cottage.css">
    <link rel="stylesheet" href="css/mobile-app.css">
</head>
<body>

<nav class="navbar navbar-light navbar-sub" id="navbar">
    <div class="nav-container">
        <a href="index.php#cottages" class="s5-back" aria-label="Back to cottages">←</a>
        <span class="s5-header-title">Cottage Details</span>
        <a href="index.php" class="nav-logo"><img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img"></a>
        <ul class="nav-links">
            <li><a href="index.php#cottages">← All Cottages</a></li>
            <li><a href="check_booking.php">My Booking</a></li>
            <li><a href="booking.php?cottage_id=<?= $id ?>" class="btn-nav">Book This</a></li>
        </ul>
        <button class="nav-toggle" id="navToggle" aria-expanded="false">☰</button>
    </div>
</nav>

<?php $active_page = 'cottages'; require 'includes/drawer.php'; ?>

<div class="cottage-detail-page">

    <div class="breadcrumb-bar">
        <div class="container">
            <a href="index.php">Home</a> <span>›</span>
            <a href="index.php#cottages">Cottages</a> <span>›</span>
            <span><?= htmlspecialchars($cottage['name']) ?></span>
        </div>
    </div>

    <div class="container cottage-detail-layout">

        <div class="cottage-detail-main">

            <div class="gallery-main single-photo">
                <img src="<?= $single_img ?>"
                     alt="<?= htmlspecialchars($cottage['name']) ?>"
                     class="main-photo" id="mainPhotoImg">
                <div class="avail-ribbon available-ribbon">
                    <span class="avail-dot"></span> Available
                </div>
                <?php if (!$photo_row): ?>
                <div class="placeholder-notice">📷 Sample image — replace via Admin → Cottages</div>
                <?php endif; ?>
            </div>

            <div class="cottage-detail-info">
                <div class="cdi-header">
                    <div class="cdi-cat-tag"><?= $cottage['category'] ?></div>
                    <h1><?= htmlspecialchars($cottage['name']) ?></h1>
                    <div class="cdi-meta">
                        <span>👥 Up to <?= $cottage['capacity'] ?> guests</span>
                        <?php if ($is_pavilion): ?>
                        <span class="cdi-meta-event">🎉 Priced per occasion</span>
                        <?php elseif ($cottage['category'] === OVERNIGHT_CATEGORY): ?>
                        <span>🌙 ₱<?= number_format($cottage['price_per_night'], 0) ?>/day or night</span>
                        <?php else: ?>
                        <span>☀️ ₱<?= number_format($cottage['price_per_night'], 0) ?>/day</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cdi-desc">
                    <h3>About this Cottage</h3>
                    <p><?= htmlspecialchars($cottage['description']) ?></p>
                </div>

                <div class="cdi-amenities">
                    <h3>Amenities & Inclusions</h3>
                    <div class="amenities-list">
                        <?php foreach ($amenities_list as $a): ?>
                        <div class="amenity-chip">
                            <span class="chip-icon">
                                <?php
                                $icons_map = [
                                    'Fan'=>'🌀','Aircon'=>'❄️','Air conditioning'=>'❄️','Split-type Aircon'=>'❄️','Central Aircon'=>'❄️',
                                    'Bathroom'=>'🚿','Hot'=>'🚿','shower'=>'🚿','Jacuzzi'=>'🛁',
                                    'Bed'=>'🛏️','King bed'=>'🛏️','Queen beds'=>'🛏️','bedding'=>'🛏️',
                                    'TV'=>'📺','Smart TV'=>'📺',
                                    'ref'=>'🧊','Mini ref'=>'🧊','Mini bar'=>'🧊',
                                    'BBQ'=>'🔥','grill'=>'🔥',
                                    'Parking'=>'🅿️',
                                    'Fan'=>'🌀','Electric Fan'=>'🌀',
                                    'Veranda'=>'🌿','Garden'=>'🌿',
                                    'tables'=>'🪑','chairs'=>'🪑',
                                    'lighting'=>'💡','lights'=>'💡',
                                    'Sound'=>'🔊','system'=>'🔊',
                                    'Mosquito'=>'🦟','net'=>'🦟',
                                    'view'=>'🏔️','View'=>'🏔️',
                                    'Breakfast'=>'🍳',
                                    'Hammock'=>'🏖️',
                                    'Balcony'=>'🌅',
                                ];
                                $chip_icon = '✅';
                                foreach ($icons_map as $kw => $ico) {
                                    if (stripos($a, $kw) !== false) { $chip_icon = $ico; break; }
                                }
                                echo $chip_icon;
                                ?>
                            </span>
                            <?= htmlspecialchars(trim($a)) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($is_pavilion): ?>
                <div class="cdi-event-pricing">
                    <h3>Event Pricing</h3>
                    <p class="event-pricing-note">The Pavilion is booked per occasion — pick the event that fits when you book.</p>
                    <div class="event-price-grid">
                        <?php foreach ($event_info as $etype => $einfo):
                            $eprice = $event_prices[$etype] ?? null;
                        ?>
                        <div class="event-price-card">
                            <div class="epc-icon"><?= $einfo['icon'] ?></div>
                            <div class="epc-body">
                                <strong><?= $etype ?></strong>
                                <span class="epc-desc"><?= $einfo['desc'] ?></span>
                            </div>
                            <div class="epc-price">
                                <?= $eprice !== null ? '₱' . number_format($eprice, 0) : 'Ask us' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cottage-detail-sidebar">
            <div class="booking-panel">
                <div class="bp-price<?= $is_pavilion ? ' is-event' : '' ?>">
                    <?php if ($is_pavilion): ?>
                    <strong>Priced per occasion</strong>
                    <span>see pricing below</span>
                    <?php elseif ($is_overnight_eligible): ?>
                    <strong>₱<?= number_format($cottage['price_per_night'], 0) ?></strong>
                    <span>per day or night</span>
                    <?php else: ?>
                    <strong>₱<?= number_format($cottage['price_per_night'], 0) ?></strong>
                    <span>per day</span>
                    <?php endif; ?>
                </div>

                <div class="bp-quick-form">
                    <h4>Check Availability</h4>
                    <form action="booking.php" method="GET">
                        <input type="hidden" name="cottage_id" value="<?= $id ?>">
                        <?php if ($is_overnight_eligible): ?>
                        <input type="hidden" name="booking_type" value="Overnight">
                        <div class="bp-field">
                            <label>Check-in</label>
                            <input type="date" name="check_in" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="bp-field">
                            <label>Check-out</label>
                            <input type="date" name="check_out" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        </div>
                        <p style="font-size:0.72rem;color:var(--text-light,#888);margin:-0.2rem 0 0.4rem;">Booking a single day instead? You can switch to Day Use on the next page.</p>
                        <?php else: ?>
                        <input type="hidden" name="booking_type" value="Day Use">
                        <div class="bp-field">
                            <label>Visit Date</label>
                            <input type="date" name="check_in" min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <?php endif; ?>
                        <div class="bp-field">
                            <label>Guests</label>
                            <select name="guests">
                                <?php for($i=1;$i<=$cottage['capacity'];$i++): ?>
                                <option value="<?=$i?>"><?=$i?> <?=$i==1?'Guest':'Guests'?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary" style="width:100%;margin-top:0.5rem;">
                            <?= $is_pavilion ? 'Book This Event →' : 'Book This Cottage →' ?>
                        </button>
                    </form>
                </div>

                <div class="bp-notes">
                    <p>💰 Pay at resort or via GCash</p>
                    <p>✅ Free cancellation 24hrs before</p>
                    <p>📞 Call us: +63 912 345 6789</p>
                </div>
            </div>

            <?php if (!empty($related)): ?>
            <div class="related-cottages">
                <h4>Other <?= $cottage['category'] ?>s</h4>
                <?php foreach ($related as $r):
                    $rt = $type_map[$r['category']] ?? 'cottage_kids_pool';
                    $rp = $db->prepare("SELECT filename FROM cottage_images WHERE cottage_id=? ORDER BY sort_order ASC LIMIT 1");
                    $rp->execute([$r['id']]);
                    $rf = $rp->fetchColumn();
                    $r_img = $rf ? "uploads/cottages/$rf" : "images/cottage_placeholder.php?type={$rt}&n={$r['id']}";
                ?>
                <a href="cottage.php?id=<?= $r['id'] ?>" class="related-card">
                    <img src="<?= $r_img ?>"
                         alt="<?= htmlspecialchars($r['name']) ?>">
                    <div class="related-info">
                        <strong><?= htmlspecialchars($r['name']) ?></strong>
                        <?php if ($is_pavilion): ?>
                        <span>Priced per occasion · Up to <?= $r['capacity'] ?> guests</span>
                        <?php elseif ($r['category'] === OVERNIGHT_CATEGORY): ?>
                        <span>₱<?= number_format($r['price_per_night'],0) ?>/day or night · Up to <?= $r['capacity'] ?> guests</span>
                        <?php else: ?>
                        <span>₱<?= number_format($r['price_per_night'],0) ?>/day · Up to <?= $r['capacity'] ?> guests</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="footer-bottom"><p>&copy; <?= date('Y') ?> S-Five Inland Resort.</p></div>
</footer>

<div class="s5-contact-footer-card">
    <img src="images/sfive_logo.png" alt="S-Five Inland Resort" class="nav-logo-img">
    <h3>S-Five Inland Resort</h3>
    <p class="tagline">Relax. Refresh. Reconnect.</p>
    <p class="s5-copyright">&copy; <?= date('Y') ?> S-Five Inland Resort. All rights reserved.</p>
</div>

<script src="js/main.js"></script>
</body>
</html>