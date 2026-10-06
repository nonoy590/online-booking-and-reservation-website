<?php
require_once 'auth.php';
$page_title = 'Reports';
$db = getDB();

$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));

$monthly_stats = $db->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN status='Confirmed' THEN 1 ELSE 0 END) AS confirmed,
           SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END) AS cancelled,
           0 AS revenue
    FROM reservations
    WHERE MONTH(created_at)=? AND YEAR(created_at)=?
");
$monthly_stats->execute([$month,$year]);
$stats=$monthly_stats->fetch();

// Revenue = verified GCash money received in the selected month (not booking totals).
$rev=$db->prepare("SELECT COALESCE(SUM(vp.amount),0)
    FROM ".verifiedPaymentsSql()." vp
    JOIN reservations r ON r.id=vp.reservation_id
    WHERE r.status<>'Cancelled' AND MONTH(vp.paid_at)=? AND YEAR(vp.paid_at)=?");
$rev->execute([$month,$year]);
$stats['revenue']=(float)$rev->fetchColumn();

// Money still to be collected on confirmed bookings (e.g. the 80% balance).
$outstanding=(float)$db->query("SELECT COALESCE(SUM(GREATEST(r.total_price-COALESCE(p.paid,0),0)),0)
    FROM reservations r
    LEFT JOIN (SELECT reservation_id, SUM(amount) AS paid FROM ".verifiedPaymentsSql()." x GROUP BY reservation_id) p ON p.reservation_id=r.id
    WHERE r.status='Confirmed' AND r.payment_option='Downpayment'")->fetchColumn();

$bookings=$db->prepare("
    SELECT r.*,
           COALESCE(c.name, CASE WHEN r.booking_type IN ('Exclusive','Exclusive Resort')
                                 THEN 'Accommodation' ELSE 'Accommodation' END) AS cottage_name
    FROM reservations r
    LEFT JOIN cottages c ON r.cottage_id=c.id
    WHERE MONTH(r.created_at)=? AND YEAR(r.created_at)=?
    ORDER BY r.created_at DESC
");
$bookings->execute([$month,$year]);
$bookings=$bookings->fetchAll();

$by_cottage=$db->prepare("
    SELECT c.name,
           (SELECT COUNT(*) FROM reservations r
             WHERE r.cottage_id=c.id AND MONTH(r.created_at)=? AND YEAR(r.created_at)=?) AS bookings,
           COALESCE((SELECT SUM(vp.amount)
             FROM ".verifiedPaymentsSql()." vp
             JOIN reservations r2 ON r2.id=vp.reservation_id
             WHERE r2.cottage_id=c.id AND r2.status<>'Cancelled'
               AND MONTH(vp.paid_at)=? AND YEAR(vp.paid_at)=?),0) AS revenue
    FROM cottages c
    ORDER BY revenue DESC
");
$by_cottage->execute([$month,$year,$month,$year]);
$by_cottage=$by_cottage->fetchAll();

$exclusive_report=$db->prepare("
    SELECT
      (SELECT COUNT(*) FROM reservations
        WHERE booking_type IN ('Exclusive','Exclusive Resort')
          AND MONTH(created_at)=? AND YEAR(created_at)=?) AS bookings,
      COALESCE((SELECT SUM(vp.amount)
        FROM ".verifiedPaymentsSql()." vp
        JOIN reservations r ON r.id=vp.reservation_id
        WHERE r.booking_type IN ('Exclusive','Exclusive Resort') AND r.status<>'Cancelled'
          AND MONTH(vp.paid_at)=? AND YEAR(vp.paid_at)=?),0) AS revenue
");
$exclusive_report->execute([$month,$year,$month,$year]);
$exclusive_report=$exclusive_report->fetch();

if ((int)$exclusive_report['bookings']>0) {
    $by_cottage[]=[
        'name'=>'🏝️ Exclusive Resort',
        'bookings'=>(int)$exclusive_report['bookings'],
        'revenue'=>(float)$exclusive_report['revenue']
    ];
}

usort($by_cottage,function($a,$b){return $b['revenue']<=>$a['revenue'];});

$all_time=$db->query("
    SELECT COUNT(*) AS total,
           (SELECT COALESCE(SUM(vp.amount),0)
              FROM ".verifiedPaymentsSql()." vp
              JOIN reservations r ON r.id=vp.reservation_id
              WHERE r.status<>'Cancelled') AS revenue
    FROM reservations
")->fetch();

$months_list=['','January','February','March','April','May','June','July','August','September','October','November','December'];
$years_list=range(date('Y'),date('Y')-3);

include 'partials/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Reports — S-Five Resort</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>

<div class="card" style="margin-bottom:1.5rem;">
<div class="card-body">
<form method="GET" class="filter-form report-filter">
<div class="form-group" style="margin-bottom:0;">
<label>Month</label>
<select name="month">
<?php for($m=1;$m<=12;$m++): ?>
<option value="<?=$m?>" <?=$month==$m?'selected':''?>><?=$months_list[$m]?></option>
<?php endfor; ?>
</select>
</div>
<div class="form-group" style="margin-bottom:0;">
<label>Year</label>
<select name="year">
<?php foreach($years_list as $y): ?>
<option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=$y?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn-filter" style="align-self:flex-end;">Generate</button>
<button type="button" onclick="window.print()" class="btn-filter" style="align-self:flex-end;background:#444;">🖨️ Print</button>
</form>
</div>
</div>

<div class="stats-grid" style="margin-bottom:1.5rem;">
<?php
$cards=[
['📋','#e8f5e9','Total Bookings',$stats['total']],
['✅','#e3f2fd','Confirmed',$stats['confirmed']],
['⏳','#fff8e1','Pending',$stats['pending']],
['❌','#fce4ec','Cancelled',$stats['cancelled']],
['💰','#f3e5f5','Monthly Revenue (collected)','₱'.number_format($stats['revenue'],0)],
['🏆','#e0f7fa','All-Time Revenue (collected)','₱'.number_format($all_time['revenue'],0)],
['🧾','#fff3e0','Balances to Collect','₱'.number_format($outstanding,0)]
];
foreach($cards as $card):
?>
<div class="stat-card">
<div class="stat-icon" style="background:<?=$card[1]?>;"><?=$card[0]?></div>
<div class="stat-info"><span><?=$card[2]?></span><strong><?=$card[3]?></strong></div>
</div>
<?php endforeach; ?>
</div>

<div class="report-grid">
<div class="card">
<div class="card-header"><h3>Revenue by Cottage — <?=$months_list[$month]?> <?=$year?></h3></div>
<div class="card-body">
<table class="admin-table">
<thead><tr><th>Cottage</th><th>Bookings</th><th>Revenue</th></tr></thead>
<tbody>
<?php foreach($by_cottage as $bc): ?>
<tr>
<td><?=htmlspecialchars($bc['name'])?></td>
<td><?=$bc['bookings']?></td>
<td>₱<?=number_format($bc['revenue'],0)?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="card">
<div class="card-header">
<h3>Guest List — <?=$months_list[$month]?> <?=$year?></h3>
<span class="count-badge"><?=count($bookings)?></span>
</div>
<div class="card-body">
<div class="table-wrap">
<table class="admin-table">
<thead><tr>
<th>Code</th><th>Guest</th><th>Cottage</th><th>Type</th><th>Check-in</th><th>Total</th><th>Status</th>
</tr></thead>
<tbody>
<?php if(empty($bookings)): ?>
<tr><td colspan="7" class="empty-row">No bookings for this month.</td></tr>
<?php endif; ?>
<?php foreach($bookings as $b):
$type=trim($b['booking_type']??'');
$is_exclusive=in_array(strtolower($type),['exclusive','exclusive resort'],true);
$display_type=$is_exclusive?'🏝️ Exclusive':$type;
?>
<tr>
<td><code><?=htmlspecialchars($b['booking_code'])?></code></td>
<td><?=htmlspecialchars($b['guest_name'])?></td>
<td><?=htmlspecialchars($b['cottage_name'])?></td>
<td><?=htmlspecialchars($display_type)?></td>
<td><?=date('M d',strtotime($b['check_in']))?></td>
<td>₱<?=number_format($b['total_price'],0)?></td>
<td><span class="badge badge-<?=strtolower($b['status'])?>"><?=htmlspecialchars($b['status'])?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<?php include 'partials/footer.php'; ?>
