<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$search = query_param('q');
$branch = query_int('location');

$where = ["r.`status` = 'pending'"];
$params = [];
if ($search !== '') {
    $where[] = '(r.`name` LIKE ? OR r.`email` LIKE ? OR p.`name` LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($branch) {
    $where[] = 'r.`location_id` = ?';
    $params[] = $branch;
}
// Applicant context: account age and what happened to their other applications
// (matched on adopter_id, or on email for applications made before accounts were linked).
$sameApplicant = "(x.`adopter_id` = r.`adopter_id` OR x.`email` = r.`email`)";
$requests = db_all(
    "SELECT r.*, p.`name` AS pet_name, p.`image`, p.`breed`, p.`created_at` AS listed_at, c.`name` AS category_name, l.`city`,
        ad.`created_at` AS member_since,
        (SELECT COUNT(*) FROM `favourites` f WHERE f.`pet_id` = r.`pet_id`) AS saves,
        (SELECT COUNT(*) FROM `requests` x WHERE x.`request_id` <> r.`request_id` AND x.`status` = 'pending' AND $sameApplicant) AS other_pending,
        (SELECT COUNT(*) FROM `requests` x WHERE x.`status` = 'rejected' AND $sameApplicant) AS prev_rejected,
        (SELECT COUNT(*) FROM `requests` x WHERE x.`status` = 'cancelled' AND $sameApplicant) AS prev_cancelled,
        (SELECT COUNT(*) FROM `accepted` x WHERE $sameApplicant) AS prev_accepted,
        (SELECT COUNT(*) FROM `adopted` x WHERE $sameApplicant) AS prev_adopted
     FROM `requests` r
     LEFT JOIN `pets` p ON p.`pet_id` = r.`pet_id`
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     LEFT JOIN `locations` l ON l.`location_id` = r.`location_id`
     LEFT JOIN `adopter` ad ON ad.`adopter_id` = r.`adopter_id`
     WHERE " . implode(' AND ', $where) . " ORDER BY r.`request_time` ASC",
    $params
);
// Summary over every pending request (ignores the search box).
$queue = db_one(
    "SELECT COUNT(*) AS n, MIN(`request_time`) AS oldest, AVG(TIMESTAMPDIFF(MINUTE, `request_time`, NOW()) / 1440) AS avg_wait,
        SUM(`request_time` < NOW() - INTERVAL 6 DAY) AS over5
     FROM `requests` WHERE `status` = 'pending'"
);
$decidedRecently = db_one(
    "SELECT AVG(d) AS avg_days FROM (
        SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `accept_time`) / 1440 AS d FROM `accepted` WHERE `accept_time` >= NOW() - INTERVAL 90 DAY
        UNION ALL SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `accept_time`) / 1440 FROM `adopted` WHERE `accept_time` >= NOW() - INTERVAL 90 DAY
        UNION ALL SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `updated_at`) / 1440 FROM `requests` WHERE `status` = 'rejected' AND `updated_at` >= NOW() - INTERVAL 90 DAY
     ) t"
);
/** "3 days", "5 h" or "—". */
function wait_label($days)
{
    if ($days === null) {
        return '—';
    }
    $days = (float) $days;
    return $days < 1 ? max(1, (int) round($days * 24)) . ' h' : (int) floor($days) . ' day' . ((int) floor($days) === 1 ? '' : 's');
}
/** Short account age, e.g. "3 months" or "5 days". */
function account_age($since)
{
    $days = (int) floor((time() - strtotime($since)) / 86400);
    if ($days < 1) {
        return 'today';
    }
    if ($days < 60) {
        return $days . ' day' . ($days === 1 ? '' : 's');
    }
    $months = (int) floor($days / 30);
    return $months < 24 ? $months . ' months' : (int) floor($days / 365) . ' years';
}

$pageTitle = 'Adoption requests';
$activeNav = 'requests';
include __DIR__ . '/header.php';
?>
<?php [$pageRows, $page, $pages, $total] = paginate($requests, 15); ?>
<div class="admin-page-head">
    <div>
        <h1>Adoption requests</h1>
        <p>New applications waiting for a decision, oldest first. Each pet is reserved while its request is open.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('admin/accepted.php')) ?>"><i class="bi bi-calendar-check"></i> Awaiting pickup</a>
</div>

<div class="admin-strip">
    <div><span class="lbl">Pending</span><strong><?= (int) $queue['n'] ?></strong><small>awaiting a decision</small></div>
    <?php $oldestDays = $queue['oldest'] ? (int) floor((time() - strtotime($queue['oldest'])) / 86400) : 0; ?>
    <div class="<?= $oldestDays > 5 ? 'is-danger' : ($oldestDays > 2 ? 'is-warn' : '') ?>"><span class="lbl">Oldest wait</span><strong><?= $queue['oldest'] ? e(wait_label((time() - strtotime($queue['oldest'])) / 86400)) : '—' ?></strong><small><?= $queue['oldest'] ? 'since ' . e(format_date($queue['oldest'])) : 'nothing waiting' ?></small></div>
    <div><span class="lbl">Average wait</span><strong><?= $queue['n'] ? e(wait_label($queue['avg_wait'])) : '—' ?></strong><small>for requests still open</small></div>
    <div class="<?= (int) $queue['over5'] ? 'is-danger' : '' ?>"><span class="lbl">Over 5 days</span><strong><?= (int) $queue['over5'] ?></strong><small>reply to these first</small></div>
    <div><span class="lbl">Usual decision time</span><strong><?= e(wait_label($decidedRecently['avg_days'] === null ? null : (float) $decidedRecently['avg_days'])) ?></strong><small>average, last 90 days</small></div>
</div>

<form class="toolbar" method="get">
    <div class="search"><i class="bi bi-search" aria-hidden="true"></i>
        <label class="visually-hidden" for="reqQ">Search applicant or pet</label>
        <input class="form-control" type="search" id="reqQ" name="q" value="<?= e($search) ?>" placeholder="Search applicant, email or pet…">
    </div>
    <select class="form-select" name="location" onchange="this.form.submit()" aria-label="Branch">
        <option value="">All branches</option>
        <?php foreach (all_locations() as $l): ?>
            <option value="<?= (int) $l['location_id'] ?>"<?= $branch === (int) $l['location_id'] ? ' selected' : '' ?>><?= e($l['city']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-dark">Search</button>
    <?php if ($search !== '' || $branch): ?><a class="btn btn-link" href="<?= e(url('admin/requests.php')) ?>"><i class="bi bi-x-lg"></i> Clear</a><?php endif; ?>
</form>

<?php if ($requests): ?>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Applicant</th><th>Applicant history</th><th>Waiting</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($pageRows as $r): $days = (int) floor((time() - strtotime($r['request_time'])) / 86400); ?>
                    <tr>
                        <td>
                            <div class="cell-main">
                                <img class="thumb" src="<?= e(pet_image_url($r['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                                <div><strong><?= e($r['pet_name'] ?? 'Unknown pet') ?></strong><small><?= e(trim(($r['category_name'] ?? '') . ($r['breed'] ? ' · ' . $r['breed'] : ''), ' ·')) ?></small>
                                    <small><i class="bi bi-geo-alt"></i> <?= e($r['city'] ?? '—') ?> · <i class="bi bi-heart"></i> <?= (int) $r['saves'] ?> saved<?= $r['listed_at'] ? ' · listed ' . (int) floor((time() - strtotime($r['listed_at'])) / 86400) . 'd ago' : '' ?></small></div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-main"><span class="avatar"><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
                                <div><strong><?= e($r['name']) ?></strong><small><?= e($r['email']) ?> · <?= e($r['contact']) ?></small></div></div>
                        </td>
                        <td>
                            <?php $previous = (int) $r['prev_rejected'] + (int) $r['prev_cancelled'] + (int) $r['prev_accepted'] + (int) $r['prev_adopted']; ?>
                            <div class="history-chips">
                                <?php if ((int) $r['prev_adopted']): ?><span class="chip chip-success"><i class="bi bi-house-heart"></i>Adopted <?= (int) $r['prev_adopted'] ?> before</span><?php endif; ?>
                                <?php if (!$previous && !(int) $r['other_pending']): ?><span class="chip"><i class="bi bi-stars"></i>First application</span><?php endif; ?>
                                <?php if ((int) $r['prev_accepted']): ?><span class="chip chip-info"><i class="bi bi-calendar-check"></i><?= (int) $r['prev_accepted'] ?> awaiting pickup</span><?php endif; ?>
                                <?php if ((int) $r['other_pending']): ?><span class="chip chip-warn"><i class="bi bi-hourglass-split"></i><?= (int) $r['other_pending'] ?> other pending</span><?php endif; ?>
                                <?php if ((int) $r['prev_rejected']): ?><span class="chip chip-danger"><i class="bi bi-x-circle"></i><?= (int) $r['prev_rejected'] ?> not approved</span><?php endif; ?>
                                <?php if ((int) $r['prev_cancelled']): ?><span class="chip"><i class="bi bi-slash-circle"></i><?= (int) $r['prev_cancelled'] ?> withdrawn</span><?php endif; ?>
                            </div>
                            <span class="sub"><?= $r['member_since'] ? 'Member for ' . e(account_age($r['member_since'])) : 'No linked account' ?></span>
                        </td>
                        <td class="text-nowrap"><span class="status-pill <?= $days > 5 ? 'status-danger' : ($days > 2 ? 'status-warning' : 'status-info') ?>" title="Amber after 2 days, red after 5"><i class="bi bi-clock"></i><?= e(wait_label((time() - strtotime($r['request_time'])) / 86400)) ?></span><span class="sub mt-1"><?= e(format_date($r['request_time'], true)) ?></span></td>
                        <td class="text-end"><a class="btn btn-primary btn-sm" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $r['pet_id'])) ?>">Review <i class="bi bi-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><?php render_pagination($page, $pages, $total, 15, 'requests'); ?></div>
    </div>
<?php elseif ($search !== '' || $branch): ?>
    <?php render_empty_state('bi-search', 'No matching requests', 'Try a different name or branch.', 'Clear filters', url('admin/requests.php')); ?>
<?php else: ?>
    <?php render_empty_state('bi-inbox', 'No pending requests', 'You’re all caught up. New adoption applications will show up here.'); ?>
<?php endif; ?>
<?php page_end(); ?>
