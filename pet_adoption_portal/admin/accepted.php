<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$rows = db_all(
    "SELECT a.*, p.`name` AS pet_name, p.`image`, p.`breed`, c.`name` AS category_name, l.`city`, l.`contact` AS branch_phone, l.`address` AS branch_address
     FROM `accepted` a
     LEFT JOIN `pets` p ON p.`pet_id` = a.`pet_id`
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     LEFT JOIN `locations` l ON l.`location_id` = a.`location_id`
     ORDER BY a.`accept_time` ASC"
);

// Pickup deadline is 3 days after approval.
$now = time();
$dueSoon = 0;
$overdueRows = 0;
foreach ($rows as &$r) {
    $r['deadline'] = strtotime($r['accept_time']) + 3 * 86400;
    $r['left'] = $r['deadline'] - $now;
    $r['chat_adopter'] = adopter_id_for($r);
    $overdueRows += $r['left'] < 0 ? 1 : 0;
    $dueSoon += $r['left'] >= 0 && $r['left'] < 86400 ? 1 : 0;
}
unset($r);
$pickupStats = db_one(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, `accept_time`, `adopt_time`) / 1440) AS avg_days,
        SUM(TIMESTAMPDIFF(MINUTE, `accept_time`, `adopt_time`) <= 3 * 1440) AS on_time, COUNT(*) AS n
     FROM `adopted`"
);
/** "2 days 4 h" style countdown for a number of seconds. */
function countdown_label($seconds)
{
    $seconds = abs($seconds);
    $days = (int) floor($seconds / 86400);
    $hours = (int) floor(($seconds % 86400) / 3600);
    if ($days === 0) {
        return max(1, $hours) . ' h';
    }
    return $days . ' day' . ($days === 1 ? '' : 's') . ($hours ? ' ' . $hours . ' h' : '');
}

$pageTitle = 'Awaiting pickup';
$activeNav = 'accepted';
include __DIR__ . '/header.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Awaiting pickup</h1>
        <p>Approved applications. Adopters have 3 days from approval to collect their pet — mark the adoption complete once they do.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('admin/requests.php')) ?>"><i class="bi bi-inbox"></i> Pending requests</a>
</div>

<div class="admin-strip">
    <div><span class="lbl">Awaiting pickup</span><strong><?= count($rows) ?></strong><small>approved, not yet collected</small></div>
    <div class="<?= $dueSoon ? 'is-warn' : '' ?>"><span class="lbl">Due within 24 h</span><strong><?= $dueSoon ?></strong><small>a reminder call may help</small></div>
    <div class="<?= $overdueRows ? 'is-danger' : '' ?>"><span class="lbl">Overdue</span><strong><?= $overdueRows ?></strong><small>past the 3-day window</small></div>
    <div><span class="lbl">Usual pickup time</span><strong><?= $pickupStats['n'] ? e(countdown_label((float) $pickupStats['avg_days'] * 86400)) : '—' ?></strong><small><?= $pickupStats['n'] ? round((int) $pickupStats['on_time'] / (int) $pickupStats['n'] * 100) . '% collected on time' : 'no adoptions yet' ?></small></div>
</div>

<?php if ($rows): ?>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Adopter</th><th>Pickup branch</th><th>Approved</th><th>Pick up by</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <div class="cell-main">
                                <img class="thumb" src="<?= e(pet_image_url($r['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                                <div><strong><?= e($r['pet_name'] ?? 'Unknown pet') ?></strong><small><?= e(trim(($r['category_name'] ?? '') . ($r['breed'] ? ' · ' . $r['breed'] : ''), ' ·')) ?></small></div>
                            </div>
                        </td>
                        <td><div class="cell-main"><span class="avatar"><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?></span><div><strong><?= e($r['name']) ?></strong><small><?= e($r['contact']) ?></small></div></div></td>
                        <td class="text-nowrap"><i class="bi bi-geo-alt text-muted"></i> <?= e($r['city'] ?? '—') ?>
                            <?php if (!empty($r['branch_phone'])): ?><a class="sub" href="tel:<?= e($r['branch_phone']) ?>"><i class="bi bi-telephone"></i> <?= e($r['branch_phone']) ?></a><?php endif; ?></td>
                        <td class="text-nowrap"><?= e(format_date($r['accept_time'])) ?><span class="sub"><?= e(time_ago($r['accept_time'])) ?></span></td>
                        <td class="text-nowrap">
                            <strong class="d-block"><?= e(date('D, M j · g:i A', $r['deadline'])) ?></strong>
                            <?php if ($r['left'] >= 0): ?>
                                <span class="status-pill <?= $r['left'] < 86400 ? 'status-warning' : 'status-info' ?>"><i class="bi bi-clock"></i><?= e(countdown_label($r['left'])) ?> left</span>
                            <?php else: ?>
                                <span class="status-pill status-danger"><i class="bi bi-exclamation-triangle"></i>Overdue by <?= e(countdown_label($r['left'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <?php if ($r['chat_adopter']): ?><a class="btn btn-light btn-sm" href="<?= e(url('admin/contact.php?view=chats&chat=' . $r['chat_adopter'])) ?>" title="Chat with <?= e($r['name']) ?>"><i class="bi bi-chat-heart"></i> Chat</a><?php endif; ?>
                            <a class="btn btn-primary btn-sm" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $r['pet_id'])) ?>">Open <i class="bi bi-arrow-right"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <?php render_empty_state('bi-calendar-check', 'Nobody is waiting for pickup', 'Approved requests appear here until the pet is collected.', 'Review requests', url('admin/requests.php')); ?>
<?php endif; ?>
<?php page_end(); ?>
