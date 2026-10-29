<?php
/*
 * Records: completed adoptions (default) and, with ?tab=adopters, the adopter directory
 * with each person's application counts.
 */
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$tab = query_param('tab') === 'adopters' ? 'adopters' : 'adoptions';
$search = query_param('q');
$city = query_param('city');

$adoptionCount = (int) db_value("SELECT COUNT(*) FROM `adopted`");
$adopterCount = (int) db_value("SELECT COUNT(*) FROM `adopter`");

if ($tab === 'adoptions') {
    $where = ['1 = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(`pet_name` LIKE ? OR `name` LIKE ? OR `email` LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if ($city !== '') {
        $where[] = '`location_city` = ?';
        $params[] = $city;
    }
    $rows = db_all(
        "SELECT *, TIMESTAMPDIFF(MINUTE, `request_time`, `adopt_time`) / 1440 AS days_total,
                TIMESTAMPDIFF(MINUTE, `accept_time`, `adopt_time`) / 1440 AS days_pickup
         FROM `adopted` WHERE " . implode(' AND ', $where) . " ORDER BY `adopt_time` DESC", $params);
    $summary = db_one(
        "SELECT COUNT(*) AS n, SUM(YEAR(`adopt_time`) = YEAR(NOW())) AS this_year,
            SUM(`adopt_time` >= NOW() - INTERVAL 30 DAY) AS last30,
            AVG(TIMESTAMPDIFF(MINUTE, `request_time`, `adopt_time`) / 1440) AS avg_days,
            COUNT(DISTINCT COALESCE(`adopter_id`, `email`)) AS families
         FROM `adopted`");
    $perBranch = db_all("SELECT `location_city` AS city, COUNT(*) AS n FROM `adopted` GROUP BY `location_city` ORDER BY n DESC, city");
    $cities = array_column(db_all("SELECT DISTINCT `location_city` FROM `adopted` ORDER BY `location_city`"), 'location_city');
    $filtered = $search !== '' || $city !== '';
} else {
    $params = [];
    $where = '';
    if ($search !== '') {
        $where = 'WHERE a.`name` LIKE ? OR a.`email` LIKE ?';
        $like = '%' . $search . '%';
        $params = [$like, $like];
    }
    // last_active = newest of: sign-up, any application event, a saved pet or a message.
    $rows = db_all(
        "SELECT a.`adopter_id`, a.`name`, a.`email`, a.`created_at`,
            GREATEST(a.`created_at`,
                COALESCE((SELECT MAX(COALESCE(r.`updated_at`, r.`request_time`)) FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id`), a.`created_at`),
                COALESCE((SELECT MAX(c.`accept_time`) FROM `accepted` c WHERE c.`adopter_id` = a.`adopter_id`), a.`created_at`),
                COALESCE((SELECT MAX(d.`adopt_time`) FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id`), a.`created_at`),
                COALESCE((SELECT MAX(f.`created_at`) FROM `favourites` f WHERE f.`adopter_id` = a.`adopter_id`), a.`created_at`),
                COALESCE((SELECT MAX(m.`timestamp`) FROM `contact` m WHERE m.`email` = a.`email`), a.`created_at`)) AS last_active,
            (SELECT COUNT(*) FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id` AND r.`status` = 'pending') AS pending,
            (SELECT COUNT(*) FROM `accepted` c WHERE c.`adopter_id` = a.`adopter_id`) AS approved,
            (SELECT COUNT(*) FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id`) AS adopted,
            (SELECT COUNT(*) FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id` AND r.`status` IN ('rejected', 'cancelled')) AS closed,
            (SELECT COUNT(*) FROM `favourites` f WHERE f.`adopter_id` = a.`adopter_id`) AS favourites
         FROM `adopter` a $where ORDER BY a.`created_at` DESC, a.`adopter_id` DESC",
        $params
    );
    $filtered = $search !== '';
    $people = db_one(
        "SELECT COUNT(*) AS n, SUM(`created_at` >= NOW() - INTERVAL 30 DAY) AS new30,
            SUM(EXISTS (SELECT 1 FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id`)) AS adopted_any,
            SUM(EXISTS (SELECT 1 FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id` AND r.`status` = 'pending')
                OR EXISTS (SELECT 1 FROM `accepted` c WHERE c.`adopter_id` = a.`adopter_id`)) AS active_now,
            SUM(NOT EXISTS (SELECT 1 FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id`)
                AND NOT EXISTS (SELECT 1 FROM `accepted` c WHERE c.`adopter_id` = a.`adopter_id`)
                AND NOT EXISTS (SELECT 1 FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id`)) AS never_applied
         FROM `adopter` a");
}
/** "4.5 days" / "6 h" / "—" from a number of days. */
function span_label($days)
{
    if ($days === null) {
        return '—';
    }
    $days = (float) $days;
    return $days < 1 ? max(1, (int) round($days * 24)) . ' h' : rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . (round($days, 1) == 1 ? '' : 's');
}
[$pageRows, $page, $pages, $total] = paginate($rows, 15);
$selfUrl = url('admin/adopted.php' . ($tab === 'adopters' ? '?tab=adopters' : ''));

$pageTitle = $tab === 'adopters' ? 'Adopters' : 'Adoption history';
$activeNav = $tab === 'adopters' ? 'adopters' : 'adopted';
include __DIR__ . '/header.php';
?>
<div class="admin-page-head">
    <div>
        <h1><?= $tab === 'adopters' ? 'Adopters' : 'Adoption history' ?></h1>
        <p><?= $tab === 'adopters' ? 'Everyone with an adopter account, and where their applications stand.' : 'Every pet that has found a home through the portal.' ?></p>
    </div>
    <nav class="seg-tabs" aria-label="Records">
        <a class="<?= $tab === 'adoptions' ? 'active' : '' ?>" href="<?= e(url('admin/adopted.php')) ?>"><i class="bi bi-house-heart"></i> Adoptions <span class="n"><?= $adoptionCount ?></span></a>
        <a class="<?= $tab === 'adopters' ? 'active' : '' ?>" href="<?= e(url('admin/adopted.php?tab=adopters')) ?>"><i class="bi bi-people"></i> Adopters <span class="n"><?= $adopterCount ?></span></a>
    </nav>
</div>

<?php if ($tab === 'adoptions'): ?>
    <div class="admin-strip">
        <div><span class="lbl">Adoptions</span><strong><?= (int) $summary['n'] ?></strong><small><?= (int) $summary['families'] ?> famil<?= (int) $summary['families'] === 1 ? 'y' : 'ies' ?> in total</small></div>
        <div><span class="lbl">This year</span><strong><?= (int) $summary['this_year'] ?></strong><small><?= (int) $summary['last30'] ?> in the last 30 days</small></div>
        <div><span class="lbl">Avg. application to home</span><strong><?= e(span_label($summary['n'] ? (float) $summary['avg_days'] : null)) ?></strong><small>request to pickup</small></div>
        <div class="strip-wide"><span class="lbl">By branch</span>
            <?php if ($perBranch): ?>
                <div class="branch-bars">
                    <?php foreach ($perBranch as $b): ?>
                        <span><em><?= e($b['city']) ?></em><i style="width:<?= round((int) $b['n'] / max(1, (int) $perBranch[0]['n']) * 100) ?>%"></i><b><?= (int) $b['n'] ?></b></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><small>No adoptions yet</small><?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="admin-strip">
        <div><span class="lbl">Adopters</span><strong><?= (int) $people['n'] ?></strong><small><?= (int) $people['new30'] ?> joined in the last 30 days</small></div>
        <div><span class="lbl">Active right now</span><strong><?= (int) $people['active_now'] ?></strong><small>pending or awaiting pickup</small></div>
        <div><span class="lbl">Have adopted</span><strong><?= (int) $people['adopted_any'] ?></strong><small><?= $people['n'] ? round((int) $people['adopted_any'] / (int) $people['n'] * 100) . '% of accounts' : 'no accounts yet' ?></small></div>
        <div class="<?= (int) $people['never_applied'] ? 'is-warn' : '' ?>"><span class="lbl">Never applied</span><strong><?= (int) $people['never_applied'] ?></strong><small>signed up but no application</small></div>
    </div>
<?php endif; ?>

<form class="toolbar" method="get">
    <?php if ($tab === 'adopters'): ?><input type="hidden" name="tab" value="adopters"><?php endif; ?>
    <div class="search"><i class="bi bi-search" aria-hidden="true"></i>
        <label class="visually-hidden" for="recQ">Search</label>
        <input class="form-control" type="search" id="recQ" name="q" value="<?= e($search) ?>" placeholder="<?= $tab === 'adopters' ? 'Search name or email…' : 'Search pet or adopter…' ?>">
    </div>
    <?php if ($tab === 'adoptions'): ?>
        <select class="form-select" name="city" onchange="this.form.submit()" aria-label="Branch">
            <option value="">All branches</option>
            <?php foreach ($cities as $c): ?>
                <option<?= $city === $c ? ' selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <button class="btn btn-dark">Search</button>
    <?php if ($filtered): ?><a class="btn btn-link" href="<?= e($selfUrl) ?>"><i class="bi bi-x-lg"></i> Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
    <?php if ($filtered): ?>
        <?php render_empty_state('bi-search', 'No matches', 'Try a different name' . ($tab === 'adoptions' ? ' or branch.' : '.'), 'Clear filters', $selfUrl); ?>
    <?php elseif ($tab === 'adopters'): ?>
        <?php render_empty_state('bi-people', 'No adopters yet', 'People who create an account on the website will be listed here.'); ?>
    <?php else: ?>
        <?php render_empty_state('bi-house-heart', 'No adoptions yet', 'Completed adoptions will be listed here.'); ?>
    <?php endif; ?>
<?php elseif ($tab === 'adoptions'): ?>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Adopter</th><th>Branch</th><th>Requested</th><th>Adopted</th><th class="num" title="From application to pickup">Took</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($pageRows as $r): ?>
                    <tr>
                        <td>
                            <div class="cell-main">
                                <img class="thumb" src="<?= e(pet_image_url($r['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                                <div><strong><?= e($r['pet_name']) ?></strong><small><?= e($r['pet_category']) ?></small></div>
                            </div>
                        </td>
                        <td><div class="cell-main"><span class="avatar"><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?></span><div><strong><?= e($r['name']) ?></strong><small><?= e($r['email']) ?></small></div></div></td>
                        <td class="text-nowrap"><i class="bi bi-geo-alt text-muted"></i> <?= e($r['location_city']) ?></td>
                        <td class="text-nowrap"><?= e(format_date($r['request_time'])) ?></td>
                        <td><span class="status-pill status-success"><i class="bi bi-house-heart"></i><?= e(format_date($r['adopt_time'])) ?></span></td>
                        <td class="num text-nowrap"><strong><?= e(span_label($r['days_total'])) ?></strong><span class="sub">pickup <?= e(span_label($r['days_pickup'])) ?> after approval</span></td>
                        <td class="text-end"><a class="btn btn-light btn-sm" href="<?= e(url('admin/requestResponse.php?aindex=' . (int) $r['adopt_id'])) ?>">View <i class="bi bi-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><?php render_pagination($page, $pages, $total, 15, 'adoptions'); ?></div>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Adopter</th><th>Joined</th><th>Last active</th><th class="num">Applications</th><th class="num">Pending</th><th class="num">Approved</th><th class="num">Adopted</th><th class="num">Closed</th><th class="num">Saved pets</th><th class="text-end"><span class="visually-hidden">Contact</span></th></tr></thead>
                <tbody>
                <?php foreach ($pageRows as $a): ?>
                    <tr>
                        <td><div class="cell-main"><span class="avatar avatar-brand"><?= e(strtoupper(mb_substr($a['name'], 0, 1))) ?></span><div><strong><?= e($a['name']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                        <td class="text-nowrap"><?= e(format_date($a['created_at'])) ?><span class="sub"><?= e(time_ago($a['created_at'])) ?></span></td>
                        <td class="text-nowrap"><?= e(time_ago($a['last_active'])) ?><span class="sub"><?= e(format_date($a['last_active'])) ?></span></td>
                        <td class="num fw-bold"><?= (int) $a['pending'] + (int) $a['approved'] + (int) $a['adopted'] + (int) $a['closed'] ?></td>
                        <td class="num"><?= $a['pending'] ? '<span class="status-pill status-warning">' . (int) $a['pending'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="num"><?= $a['approved'] ? '<span class="status-pill status-info">' . (int) $a['approved'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="num"><?= $a['adopted'] ? '<span class="status-pill status-success">' . (int) $a['adopted'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="num"><?= (int) $a['closed'] ?></td>
                        <td class="num"><?= (int) $a['favourites'] ?></td>
                        <td class="text-end"><a class="btn btn-icon btn-sm btn-light" href="mailto:<?= e($a['email']) ?>" title="Email <?= e($a['name']) ?>" aria-label="Email <?= e($a['name']) ?>"><i class="bi bi-envelope"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><?php render_pagination($page, $pages, $total, 15, 'adopters'); ?></div>
    </div>
<?php endif; ?>
<?php page_end(); ?>
