<?php
require_once 'auth.php';
$page_title = 'Manage Gallery';
$db  = getDB();
$msg = '';
$categories = galleryCategories();

if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $row = $db->prepare("SELECT filename FROM gallery_images WHERE id=?");
    $row->execute([$id]);
    $photo = $row->fetch();
    if ($photo) {
        $path = '../uploads/gallery/' . $photo['filename'];
        if (file_exists($path)) @unlink($path);
        $db->prepare("DELETE FROM gallery_images WHERE id=?")->execute([$id]);
        $msg = 'success:Photo deleted.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $category = in_array($_POST['category'] ?? '', $categories) ? $_POST['category'] : $categories[0];
    $caption  = clean($_POST['caption'] ?? '');

    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    $upload_dir = '../uploads/gallery/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $next_sort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM gallery_images")->fetchColumn();

    $names = $_FILES['photos']['name'];
    $ok = 0; $failed = 0;

    foreach ($names as $i => $name) {
        if (empty($name) || $_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $type = $_FILES['photos']['type'][$i];
        $size = $_FILES['photos']['size'][$i];
        if (!in_array($type, $allowed) || $size > $max_size) { $failed++; continue; }

        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $ext      = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? $ext : 'jpg';
        $filename = 'gallery_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest     = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $dest)) {
            $next_sort++;
            $db->prepare("INSERT INTO gallery_images (category, filename, caption, sort_order) VALUES (?,?,?,?)")
               ->execute([$category, $filename, $caption ?: null, $next_sort]);
            $ok++;
        } else {
            $failed++;
        }
    }

    if ($ok && !$failed)      $msg = "success:$ok photo(s) added to \"$category\".";
    elseif ($ok && $failed)   $msg = "success:$ok photo(s) added, $failed skipped (must be JPG/PNG/GIF/WEBP, under 5MB).";
    else                      $msg = 'error:No photos were uploaded — check file type (JPG/PNG/GIF/WEBP) and size (max 5MB).';
}

$all_photos = $db->query("SELECT * FROM gallery_images ORDER BY category ASC, sort_order ASC, id DESC")->fetchAll();
$grouped = [];
foreach ($all_photos as $p) { $grouped[$p['category']][] = $p; }

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

include 'partials/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Gallery — S-Five Resort</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>

<?php if ($msg_text): ?>
<div class="alert-<?= $msg_type ?>"><?= htmlspecialchars($msg_text) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:2rem;">
    <div class="card-header">
        <h3>📷 Add Photos</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <div class="form-row-2">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-hint">Guests will filter photos by this category on the website.</small>
                </div>
                <div class="form-group">
                    <label>Photo Name <small>(applied to all photos in this batch)</small></label>
                    <input type="text" name="caption" placeholder="e.g. Adult Pool at Sunset" maxlength="150">
                </div>
            </div>

            <div class="form-group">
                <label>Photos</label>
                <div class="file-upload-box">
                    <input type="file" name="photos[]" id="galleryPhotosInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple required onchange="showGalleryFileCount(this)">
                    <div class="file-upload-ui" onclick="document.getElementById('galleryPhotosInput').click()">
                        <span class="upload-icon">🖼️</span>
                        <p><strong>Click to upload</strong> or drag &amp; drop — you can select several at once</p>
                        <small>JPG, PNG, GIF, WEBP — max 5MB each</small>
                    </div>
                    <p id="galleryFileCount" class="field-hint" style="display:none;"></p>
                </div>
            </div>

            <button type="submit" class="btn-save">➕ Upload to Gallery</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Gallery Photos <span class="count-badge"><?= count($all_photos) ?></span></h3>
    </div>
    <div class="card-body">
        <?php if (empty($all_photos)): ?>
        <p class="empty-row">No gallery photos yet. Upload some above — pools, garden, cottages, events, drone shots, anything guests would love to see.</p>
        <?php else: ?>
            <div class="gallery-admin-grid">
                <?php foreach ($all_photos as $p): $photo_name = $p['caption'] ?: 'Untitled'; ?>
                <div class="gallery-admin-item">
                    <img src="../uploads/gallery/<?= htmlspecialchars($p['filename']) ?>" alt="<?= htmlspecialchars($photo_name) ?>">
                    <div class="gallery-admin-caption"><?= htmlspecialchars($photo_name) ?></div>
                    <a href="gallery.php?delete=<?= $p['id'] ?>" class="btn-del-thumb" data-confirm="Delete this photo?">🗑️ Delete</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showGalleryFileCount(input) {
    const el = document.getElementById('galleryFileCount');
    if (input.files && input.files.length) {
        el.style.display = 'block';
        el.textContent = input.files.length + ' file(s) selected.';
    } else {
        el.style.display = 'none';
    }
}
</script>

<?php include 'partials/footer.php'; ?>
