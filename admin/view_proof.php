<?php
require_once 'auth.php';
$page_title = 'Payment Proof';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT g.*, r.booking_code, r.guest_name, r.check_in, r.check_out, r.booking_type,
           CASE WHEN r.booking_type IN ('Exclusive', 'Exclusive Resort')
                THEN 'Accommodation' ELSE c.name END AS cottage_name
    FROM gcash_payments g
    LEFT JOIN reservations r ON g.reservation_id = r.id
    LEFT JOIN cottages c ON r.cottage_id = c.id
    WHERE g.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();

include 'partials/header.php';
?>

<style>
.proof-back-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #fff; border: 1.5px solid var(--border); color: var(--text-mid);
    padding: 0.5rem 1rem; border-radius: 8px; font-family: 'Jost', sans-serif;
    font-size: 0.88rem; font-weight: 600; text-decoration: none; cursor: pointer;
}
.proof-back-btn:hover { background: var(--green-mid); border-color: var(--green-mid); color: #fff; }
.proof-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
.proof-section h5 { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 0.5rem; }
.proof-section p { font-size: 0.85rem; color: #444; margin-bottom: 0.2rem; }
</style>

<a href="gcash.php" class="proof-back-btn">← Back to GCash Payments</a>
<?php
$proof_file = $p['proof_image'] ?? '';
$proof_ok   = $proof_file && is_file(__DIR__ . '/../uploads/gcash/' . basename($proof_file));
?>
<?php if (!$p || !$proof_file): ?>
    <div class="card" style="margin-top:1.25rem;">
        <div class="card-body" style="padding:2.5rem;text-align:center;color:#888;">
            Screenshot not found.
        </div>
    </div>
<?php else: ?>
    <div class="card" style="margin-top:1.25rem;">
        <div class="card-header">
            <h3>Proof of Payment <span class="count-badge"><?= htmlspecialchars($p['booking_code'] ?? 'N/A') ?></span></h3>
        </div>
        <div class="card-body">
            <div class="proof-grid">
                <div class="proof-section">
                    <h5>Guest</h5>
                    <p><strong><?= htmlspecialchars($p['guest_name'] ?? 'Reservation removed') ?></strong></p>
                </div>
                <div class="proof-section">
                    <h5>Booking</h5>
                    <p><?= htmlspecialchars($p['cottage_name'] ?? '—') ?></p>
                    <?php if (!empty($p['check_in'])): ?>
                    <?php if (in_array(strtolower(trim($p['booking_type'] ?? '')), ['exclusive','exclusive resort'], true)): ?>
                    <p>Event Date: <?= date('M d, Y', strtotime($p['check_in'])) ?></p>
                    <?php else: ?>
                    <p><?= date('M d', strtotime($p['check_in'])) ?> → <?= date('M d, Y', strtotime($p['check_out'])) ?></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="proof-section">
                    <h5>GCash Reference</h5>
                    <p><strong><?= htmlspecialchars($p['reference_number']) ?></strong></p>
                </div>
                <div class="proof-section">
                    <h5>Amount</h5>
                    <p><strong>₱<?= number_format($p['amount'], 2) ?></strong></p>
                </div>
            </div>

            <div style="text-align:center;background:#f8f9fa;border:1px solid var(--border);border-radius:10px;padding:1rem;">
                <?php if (!$proof_ok): ?>
                <p style="color:#c0392b;">The screenshot file <code><?= htmlspecialchars($proof_file) ?></code> is missing from uploads/gcash/.</p>
                <?php else: ?>
                <img src="../uploads/gcash/<?= rawurlencode(basename($proof_file)) ?>"
                     alt="GCash payment proof"
                     style="max-width:100%;max-height:75vh;border-radius:8px;">
                <?php endif; ?>
            </div>

            <a href="gcash.php" class="proof-back-btn" style="margin-top:1.5rem;">← Back to GCash Payments</a>
        </div>
    </div>
<?php endif; ?>

<?php include 'partials/footer.php'; ?>