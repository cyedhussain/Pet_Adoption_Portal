<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$stats = [
    'adopters'    => (int) db_value("SELECT COUNT(*) FROM `adopter`"),
    'newAdopters' => (int) db_value("SELECT COUNT(*) FROM `adopter` WHERE `created_at` >= NOW() - INTERVAL 30 DAY"),
    'pets'        => (int) db_value("SELECT COUNT(*) FROM `pets`"),
    'available'   => (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0"),
    'adopted'     => (int) db_value("SELECT COUNT(*) FROM `adopted`"),
    'adoptedMonth'=> (int) db_value("SELECT COUNT(*) FROM `adopted` WHERE `adopt_time` >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
    'pending'     => (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `status` = 'pending'"),
    'accepted'    => (int) db_value("SELECT COUNT(*) FROM `accepted`"),
    'rejected'    => (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `status` = 'rejected'"),
    'cancelled'   => (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `status` = 'cancelled'"),
    'messages'    => (int) db_value("SELECT COUNT(*) FROM `contact` WHERE `answer` = ''"),
];
$stats['inProgress'] = $stats['pets'] - $stats['available'];

// Applications received and adoptions completed per month, last 6 months, zero-filled.
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = ['apps' => 0, 'adopted' => 0];
}
$since = array_key_first($months) . '-01';
$appRows = db_all(
    "SELECT ym, COUNT(*) AS n FROM (
        SELECT DATE_FORMAT(`request_time`, '%Y-%m') AS ym FROM `requests` WHERE `request_time` >= ?
        UNION ALL SELECT DATE_FORMAT(`request_time`, '%Y-%m') FROM `accepted` WHERE `request_time` >= ?
        UNION ALL SELECT DATE_FORMAT(`request_time`, '%Y-%m') FROM `adopted` WHERE `request_time` >= ?
     ) t GROUP BY ym",
    [$since, $since, $since]
);
foreach ($appRows as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['apps'] = (int) $row['n'];
    }
}
foreach (db_all("SELECT DATE_FORMAT(`adopt_time`, '%Y-%m') AS ym, COUNT(*) AS n FROM `adopted` WHERE `adopt_time` >= ? GROUP BY ym", [$since]) as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['adopted'] = (int) $row['n'];
    }
}
$monthLabels = array_map(fn($ym) => date('M', strtotime($ym . '-01')), array_keys($months));
$appSeries = array_column(array_values($months), 'apps');
$adoptSeries = array_column(array_values($months), 'adopted');

$byCategory = db_all(
    "SELECT c.`category_id`, c.`name`, COUNT(p.`pet_id`) AS n FROM `categories` c
     LEFT JOIN `pets` p ON p.`category_id` = c.`category_id` AND p.`requested` = 0
     GROUP BY c.`category_id`, c.`name` ORDER BY n DESC, c.`name`"
);
$maxCategory = max(1, ...array_map('intval', array_column($byCategory, 'n') ?: [0]));

$branches = db_all(
    "SELECT l.`location_id`, l.`city`, l.`address`,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`location_id` = l.`location_id` AND p.`requested` = 0) AS available,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`location_id` = l.`location_id` AND r.`status` = 'pending') AS pending,
        (SELECT COUNT(*) FROM `accepted` a WHERE a.`location_id` = l.`location_id`) AS accepted,
        (SELECT COUNT(*) FROM `adopted` d WHERE d.`location_city` = l.`city`) AS adopted,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, d.`request_time`, d.`adopt_time`) / 1440) FROM `adopted` d WHERE d.`location_city` = l.`city`) AS avg_adopt
     FROM `locations` l ORDER BY l.`city`"
);

$latestRequests = db_all(
    "SELECT r.`pet_id`, r.`name`, r.`request_time`, p.`name` AS pet_name, p.`image`, l.`city`
     FROM `requests` r
     LEFT JOIN `pets` p ON p.`pet_id` = r.`pet_id`
     LEFT JOIN `locations` l ON l.`location_id` = r.`location_id`
     WHERE r.`status` = 'pending' ORDER BY r.`request_time` ASC LIMIT 4"
);
$overdue = db_all(
    "SELECT a.`pet_id`, a.`name`, a.`accept_time`, p.`name` AS pet_name, p.`image`
     FROM `accepted` a LEFT JOIN `pets` p ON p.`pet_id` = a.`pet_id`
     WHERE a.`accept_time` < NOW() - INTERVAL 3 DAY ORDER BY a.`accept_time` ASC LIMIT 3"
);
$latestMessages = db_all("SELECT * FROM `contact` WHERE `answer` = '' ORDER BY `timestamp` ASC LIMIT 3");
$recentAdopters = db_all(
    "SELECT a.*, (SELECT COUNT(*) FROM `requests` r WHERE r.`adopter_id` = a.`adopter_id`)
                + (SELECT COUNT(*) FROM `accepted` c WHERE c.`adopter_id` = a.`adopter_id`)
                + (SELECT COUNT(*) FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id`) AS apps
     FROM `adopter` a ORDER BY a.`created_at` DESC, a.`adopter_id` DESC LIMIT 5"
);

// Recent activity: every kind of event, newest first.
$activity = [];
foreach (db_all("SELECT r.*, p.`name` AS pet_name FROM `requests` r LEFT JOIN `pets` p ON p.`pet_id` = r.`pet_id` ORDER BY COALESCE(r.`updated_at`, r.`request_time`) DESC LIMIT 8") as $r) {
    $pet = $r['pet_name'] ?? 'a pet';
    if ($r['status'] === 'pending') {
        $activity[] = [$r['request_time'], 'inbox', 'tone-warning', '<strong>' . e($r['name']) . '</strong> applied to adopt <strong>' . e($pet) . '</strong>'];
    } elseif ($r['status'] === 'rejected') {
        $activity[] = [$r['updated_at'] ?? $r['request_time'], 'x-circle', 'tone-danger', 'Application from <strong>' . e($r['name']) . '</strong> for <strong>' . e($pet) . '</strong> was not approved'];
    } elseif ($r['status'] === 'cancelled') {
        $activity[] = [$r['updated_at'] ?? $r['request_time'], 'slash-circle', 'tone-ink', '<strong>' . e($r['name']) . '</strong> cancelled their application for <strong>' . e($pet) . '</strong>'];
    }
}
foreach (db_all("SELECT a.*, p.`name` AS pet_name FROM `accepted` a LEFT JOIN `pets` p ON p.`pet_id` = a.`pet_id` ORDER BY a.`accept_time` DESC LIMIT 5") as $r) {
    $activity[] = [$r['accept_time'], 'calendar-check', 'tone-info', '<strong>' . e($r['name']) . '</strong> was approved to adopt <strong>' . e($r['pet_name'] ?? 'a pet') . '</strong>'];
}
foreach (db_all("SELECT * FROM `adopted` ORDER BY `adopt_time` DESC LIMIT 5") as $r) {
    $activity[] = [$r['adopt_time'], 'house-heart', 'tone-success', '<strong>' . e($r['pet_name']) . '</strong> went home with <strong>' . e($r['name']) . '</strong>'];
}
foreach (db_all("SELECT `name`, `created_at` FROM `adopter` ORDER BY `created_at` DESC LIMIT 5") as $r) {
    $activity[] = [$r['created_at'], 'person-plus', 'tone-brand', '<strong>' . e($r['name']) . '</strong> created an adopter account'];
}
foreach (db_all("SELECT `name`, `timestamp` FROM `contact` ORDER BY `timestamp` DESC LIMIT 5") as $r) {
    $activity[] = [$r['timestamp'], 'chat-dots', 'tone-sun', '<strong>' . e($r['name']) . '</strong> sent a message'];
}
usort($activity, fn($a, $b) => strcmp($b[0], $a[0]));
$activity = array_slice($activity, 0, 7);

$pipeline = [
    ['Pending review', $stats['pending'], '#b7791f', 'admin/requests.php', 'hourglass-split'],
    ['Awaiting pickup', $stats['accepted'], '#2563c9', 'admin/accepted.php', 'calendar-check'],
    ['Adopted', $stats['adopted'], '#198754', 'admin/adopted.php', 'house-heart'],
    ['Not approved', $stats['rejected'], '#c9372c', null, 'x-circle'],
    ['Cancelled', $stats['cancelled'], '#8b9590', null, 'slash-circle'],
];
$pipelineTotal = max(1, array_sum(array_column($pipeline, 1)));

// ---------------------------------------------------------------- insights

if (!function_exists('admin_days_label')) {
    /** "2.5 days", "6 h" or "—" for a duration measured in days (null = no data yet). */
    function admin_days_label($days)
    {
        if ($days === null) {
            return '—';
        }
        $days = (float) $days;
        if ($days < 1) {
            return max(1, (int) round($days * 24)) . ' h';
        }
        return rtrim(rtrim(number_format($days, 1), '0'), '.') . ' day' . (round($days, 1) == 1 ? '' : 's');
    }
}
if (!function_exists('admin_pct')) {
    /** Whole-number percentage, or null when there is nothing to divide by. */
    function admin_pct($part, $whole)
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }
}
/** Up / down / flat pill comparing the last 30 days with the 30 days before. */
function trend_pill($now, $before)
{
    $diff = $now - $before;
    if ($diff > 0) {
        return '<span class="delta delta-up" title="' . $before . ' in the previous 30 days"><i class="bi bi-arrow-up-short"></i>+' . $diff . '</span>';
    }
    if ($diff < 0) {
        return '<span class="delta delta-down" title="' . $before . ' in the previous 30 days"><i class="bi bi-arrow-down-short"></i>' . $diff . '</span>';
    }
    return '<span class="delta delta-flat" title="' . $before . ' in the previous 30 days">=</span>';
}

// Last 30 days vs the 30 days before that.
$window = fn($table, $col) => db_one(
    "SELECT SUM(`$col` >= NOW() - INTERVAL 30 DAY) AS now30,
            SUM(`$col` < NOW() - INTERVAL 30 DAY AND `$col` >= NOW() - INTERVAL 60 DAY) AS prev30
     FROM `$table`"
);
$trend = [
    'adopters' => $window('adopter', 'created_at'),
    'pets'     => $window('pets', 'created_at'),
    'adopted'  => $window('adopted', 'adopt_time'),
];
$appsWindow = ['now30' => 0, 'prev30' => 0];
foreach ([['requests', 'request_time'], ['accepted', 'request_time'], ['adopted', 'request_time']] as [$t, $c]) {
    $w = $window($t, $c);
    $appsWindow['now30'] += (int) $w['now30'];
    $appsWindow['prev30'] += (int) $w['prev30'];
}
$trend['apps'] = $appsWindow;

// Shelter performance. Approved = everything that reached `accepted` (still waiting or adopted).
$approvedCount = $stats['accepted'] + $stats['adopted'];
$decided = $approvedCount + $stats['rejected'];
$allApps = $approvedCount + $stats['rejected'] + $stats['cancelled'] + $stats['pending'];
$decision = db_one(
    "SELECT AVG(d) AS avg_days, COUNT(*) AS n FROM (
        SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `accept_time`) / 1440 AS d FROM `accepted`
        UNION ALL SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `accept_time`) / 1440 FROM `adopted`
        UNION ALL SELECT TIMESTAMPDIFF(MINUTE, `request_time`, `updated_at`) / 1440 FROM `requests` WHERE `status` = 'rejected' AND `updated_at` IS NOT NULL
     ) t"
);
$adoptSpeed = db_one(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, `request_time`, `adopt_time`) / 1440) AS avg_days,
            SUM(TIMESTAMPDIFF(MINUTE, `accept_time`, `adopt_time`) <= 3 * 1440) AS on_time, COUNT(*) AS n
     FROM `adopted`"
);
$performance = [
    ['Approval rate', ($r = admin_pct($approvedCount, $decided)) === null ? '—' : $r . '%', 'check2-circle', 'tone-success',
        'Approved ÷ all applications that got a decision', $decided],
    ['Time to a decision', admin_days_label($decision['n'] ? $decision['avg_days'] : null), 'stopwatch', 'tone-info',
        'Average from application to approval or rejection', (int) $decision['n']],
    ['Application to home', admin_days_label($adoptSpeed['n'] ? $adoptSpeed['avg_days'] : null), 'house-heart', 'tone-brand',
        'Average from application to the day the pet went home', (int) $adoptSpeed['n']],
    ['On-time pickups', ($r = admin_pct((int) $adoptSpeed['on_time'], (int) $adoptSpeed['n'])) === null ? '—' : $r . '%', 'calendar-check', 'tone-sun',
        'Adoptions collected within 3 days of approval', (int) $adoptSpeed['n']],
    ['Cancellation rate', ($r = admin_pct($stats['cancelled'], $allApps)) === null ? '—' : $r . '%', 'slash-circle', 'tone-ink',
        'Applications the adopter withdrew ÷ all applications', $allApps],
];

// Pets waiting longest, and the ones adopters save most.
$longestWaiting = db_all(
    "SELECT p.`pet_id`, p.`name`, p.`image`, p.`created_at`, c.`name` AS category_name, l.`city`,
        (SELECT COUNT(*) FROM `favourites` f WHERE f.`pet_id` = p.`pet_id`) AS saves,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`pet_id` = p.`pet_id`) AS apps
     FROM `pets` p
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     LEFT JOIN `locations` l ON l.`location_id` = p.`location_id`
     WHERE p.`requested` = 0 ORDER BY p.`created_at` ASC, p.`pet_id` ASC LIMIT 5"
);
$mostLoved = db_all(
    "SELECT p.`pet_id`, p.`name`, p.`image`, p.`requested`, c.`name` AS category_name, COUNT(f.`fav_id`) AS saves,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`pet_id` = p.`pet_id`)
        + (SELECT COUNT(*) FROM `accepted` a WHERE a.`pet_id` = p.`pet_id`) AS apps
     FROM `favourites` f
     JOIN `pets` p ON p.`pet_id` = f.`pet_id`
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     GROUP BY p.`pet_id` ORDER BY saves DESC, p.`name` LIMIT 5"
);

// Per animal type: pipeline counts and adoption speed.
$byType = db_all(
    "SELECT c.`category_id`, c.`name`,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id`) AS listed,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS available,
        (SELECT AVG(DATEDIFF(NOW(), p.`created_at`)) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS avg_in_care,
        (SELECT COUNT(*) FROM `requests` r JOIN `pets` p ON p.`pet_id` = r.`pet_id` WHERE p.`category_id` = c.`category_id`)
        + (SELECT COUNT(*) FROM `accepted` a JOIN `pets` p ON p.`pet_id` = a.`pet_id` WHERE p.`category_id` = c.`category_id`)
        + (SELECT COUNT(*) FROM `adopted` d WHERE d.`pet_category` = c.`name`) AS apps,
        (SELECT COUNT(*) FROM `adopted` d WHERE d.`pet_category` = c.`name`) AS adopted,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, d.`request_time`, d.`adopt_time`) / 1440) FROM `adopted` d WHERE d.`pet_category` = c.`name`) AS avg_adopt
     FROM `categories` c ORDER BY listed DESC, c.`name`"
);

$pageTitle = 'Dashboard';
$activeNav = 'home';
include __DIR__ . '/header.php';
$hour = (int) date('G');
?>
<div class="admin-page-head">
    <div>
        <h1>Good <?= $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening') ?>, <?= e(current_user_name()) ?></h1>
        <p>Here’s what is happening across your shelters today, <?= e(date('l, F j')) ?>.</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-light" href="<?= e(url('admin/requests.php')) ?>"><i class="bi bi-inbox"></i> Review requests<?php if ($stats['pending']): ?> <span class="nav-badge"><?= $stats['pending'] ?></span><?php endif; ?></a>
        <a class="btn btn-primary" href="<?= e(url('admin/pets.php?add=1')) ?>"><i class="bi bi-plus-lg"></i> Add a pet</a>
    </div>
</div>

<div class="kpi-grid mb-3">
    <a class="kpi-card" href="<?= e(url('admin/adopted.php?tab=adopters')) ?>">
        <div class="kpi-top"><span class="kpi-label">Registered adopters</span><span class="icon tone-brand"><i class="bi bi-people"></i></span></div>
        <div class="kpi-value"><?= $stats['adopters'] ?></div>
        <div class="kpi-foot"><span class="delta <?= $stats['newAdopters'] ? 'delta-up' : 'delta-flat' ?>"><i class="bi bi-arrow-up-short"></i><?= $stats['newAdopters'] ?></span> new in the last 30 days</div>
        <div class="kpi-trend"><?= trend_pill((int) $trend['adopters']['now30'], (int) $trend['adopters']['prev30']) ?> sign-ups vs previous 30 days</div>
    </a>
    <a class="kpi-card" href="<?= e(url('admin/pets.php')) ?>">
        <div class="kpi-top"><span class="kpi-label">Pets listed</span><span class="icon tone-accent"><i class="bi bi-heart"></i></span></div>
        <div class="kpi-value"><?= $stats['pets'] ?></div>
        <div>
            <div class="split-bar mb-2" role="img" aria-label="<?= $stats['available'] ?> available, <?= $stats['inProgress'] ?> with an application">
                <?php if ($stats['pets']): ?>
                    <span style="width:<?= round($stats['available'] / $stats['pets'] * 100, 1) ?>%;background:#119174"></span>
                    <span style="width:<?= round($stats['inProgress'] / $stats['pets'] * 100, 1) ?>%;background:#e2622f"></span>
                <?php endif; ?>
            </div>
            <div class="kpi-foot"><span><i class="bi bi-square-fill" style="color:#119174;font-size:.6rem"></i> <?= $stats['available'] ?> available</span><span class="ms-2"><i class="bi bi-square-fill" style="color:#e2622f;font-size:.6rem"></i> <?= $stats['inProgress'] ?> in progress</span></div>
        </div>
        <div class="kpi-trend"><?= trend_pill((int) $trend['pets']['now30'], (int) $trend['pets']['prev30']) ?> new listings vs previous 30 days</div>
    </a>
    <a class="kpi-card" href="<?= e(url('admin/adopted.php')) ?>">
        <div class="kpi-top"><span class="kpi-label">Pets adopted</span><span class="icon tone-success"><i class="bi bi-house-heart"></i></span></div>
        <div class="kpi-value"><?= $stats['adopted'] ?></div>
        <div class="kpi-foot"><span class="delta <?= $stats['adoptedMonth'] ? 'delta-up' : 'delta-flat' ?>"><?= $stats['adoptedMonth'] ?></span> completed this month</div>
        <div class="kpi-trend"><?= trend_pill((int) $trend['adopted']['now30'], (int) $trend['adopted']['prev30']) ?> adoptions vs previous 30 days</div>
    </a>
    <a class="kpi-card" href="<?= e(url('admin/requests.php')) ?>">
        <div class="kpi-top"><span class="kpi-label">Pending applications</span><span class="icon tone-warning"><i class="bi bi-hourglass-split"></i></span></div>
        <div class="kpi-value"><?= $stats['pending'] ?></div>
        <div class="kpi-foot"><?= $stats['pending'] ? '<span class="delta delta-warn">Needs review</span>' : '<span class="delta delta-up"><i class="bi bi-check2"></i> All reviewed</span>' ?></div>
        <div class="kpi-trend"><?= trend_pill((int) $trend['apps']['now30'], (int) $trend['apps']['prev30']) ?> applications received vs previous 30 days</div>
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="panel">
            <div class="panel-head">
                <div><h2>Applications &amp; adoptions</h2><p>Last 6 months · <?= array_sum($appSeries) ?> applications received, <?= array_sum($adoptSeries) ?> adoptions completed</p></div>
                <div class="chart-legend" aria-hidden="true"><span><i style="background:#e2622f"></i>Applications</span><span><i style="background:#119174"></i>Adoptions</span></div>
            </div>
            <div class="chart-box"><canvas id="activityChart" role="img" aria-label="Bar chart of applications received and adoptions completed per month for the last 6 months"></canvas></div>
            <table class="visually-hidden">
                <caption>Applications and adoptions per month</caption>
                <thead><tr><th>Month</th><th>Applications</th><th>Adoptions</th></tr></thead>
                <tbody><?php foreach (array_keys($months) as $i => $ym): ?><tr><td><?= e(date('F Y', strtotime($ym . '-01'))) ?></td><td><?= $appSeries[$i] ?></td><td><?= $adoptSeries[$i] ?></td></tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel">
            <div class="panel-head"><div><h2>Application pipeline</h2><p>Every application, by where it is now</p></div></div>
            <div class="pipeline-bar" role="img" aria-label="Application pipeline">
                <?php foreach ($pipeline as [$label, $n, $color]): ?>
                    <?php if ($n): ?><span style="width:<?= round($n / $pipelineTotal * 100, 1) ?>%;background:<?= $color ?>" title="<?= e($label) ?>: <?= $n ?>"></span><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <ul class="pipeline-list">
                <?php foreach ($pipeline as [$label, $n, $color, $href, $icon]): ?>
                    <li>
                        <<?= $href ? 'a href="' . e(url($href)) . '"' : 'div' ?>>
                            <span class="lbl"><i class="sw" style="background:<?= $color ?>"></i><?= e($label) ?></span>
                            <strong><?= $n ?></strong>
                        </<?= $href ? 'a' : 'div' ?>>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="panel">
            <div class="panel-head"><div><h2>Shelter performance</h2><p>All-time figures from every application on record</p></div></div>
            <div class="metric-grid">
                <?php foreach ($performance as [$label, $value, $icon, $tone, $hint, $n]): ?>
                    <div class="metric">
                        <span class="icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                        <div class="metric-value"><?= e($value) ?></div>
                        <div class="metric-label"><?= e($label) ?></div>
                        <p class="metric-hint"><?= e($hint) ?>. <?= $n ? 'Based on ' . $n . ' record' . ($n === 1 ? '' : 's') . '.' : 'No data yet.' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel">
            <div class="panel-head"><div><h2>Longest waiting for a home</h2><p>Available pets, by days in your care</p></div><a class="panel-link" href="<?= e(url('admin/pets.php?status=available&sort=longest')) ?>">All <i class="bi bi-arrow-right"></i></a></div>
            <?php if (!$longestWaiting): ?>
                <?php render_empty_state('bi-house-heart', 'No pets waiting', 'Every listed pet has an application in progress.', '', '', true); ?>
            <?php else: ?>
                <ul class="attention-list">
                    <?php foreach ($longestWaiting as $p): $inCare = (int) floor((time() - strtotime($p['created_at'])) / 86400); ?>
                        <li>
                            <img class="thumb" src="<?= e(pet_image_url($p['image'])) ?>" alt=""<?= img_fallback() ?>>
                            <div class="text"><strong><?= e($p['name']) ?></strong><small><?= e(trim(($p['category_name'] ?? '') . ' · ' . ($p['city'] ?? ''), ' ·')) ?> · <i class="bi bi-heart"></i> <?= (int) $p['saves'] ?> · <?= (int) $p['apps'] ?> past application<?= (int) $p['apps'] === 1 ? '' : 's' ?></small></div>
                            <span class="status-pill <?= $inCare > 60 ? 'status-danger' : ($inCare > 30 ? 'status-warning' : 'status-info') ?>"><?= $inCare ?> day<?= $inCare === 1 ? '' : 's' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4 col-lg-6">
        <div class="panel">
            <div class="panel-head"><h2>Needs your attention</h2><?php if ($stats['pending'] + $stats['messages'] + count($overdue)): ?><span class="status-pill status-warning"><?= $stats['pending'] + $stats['messages'] + count($overdue) ?> open</span><?php endif; ?></div>
            <?php if (!$latestRequests && !$overdue && !$latestMessages): ?>
                <?php render_empty_state('bi-check2-all', 'All caught up', 'No pending requests, overdue pickups or unanswered messages.', '', '', true); ?>
            <?php endif; ?>
            <?php if ($latestRequests): ?>
                <h3 class="section-label">Pending requests · oldest first</h3>
                <ul class="attention-list mb-3">
                    <?php foreach ($latestRequests as $r): ?>
                        <li>
                            <img class="thumb" src="<?= e(pet_image_url($r['image'])) ?>" alt=""<?= img_fallback() ?>>
                            <div class="text"><strong><?= e($r['name']) ?></strong> → <?= e($r['pet_name'] ?? 'a pet') ?><small><?= e($r['city'] ?? '') ?> · <?= e(time_ago($r['request_time'])) ?></small></div>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $r['pet_id'])) ?>">Review</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($overdue): ?>
                <h3 class="section-label">Overdue pickups</h3>
                <ul class="attention-list mb-3">
                    <?php foreach ($overdue as $r): ?>
                        <li>
                            <img class="thumb" src="<?= e(pet_image_url($r['image'])) ?>" alt=""<?= img_fallback() ?>>
                            <div class="text"><strong><?= e($r['pet_name'] ?? 'A pet') ?></strong> for <?= e($r['name']) ?><small>Approved <?= e(time_ago($r['accept_time'])) ?></small></div>
                            <a class="btn btn-sm btn-light" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $r['pet_id'])) ?>">Open</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($latestMessages): ?>
                <h3 class="section-label">Unanswered messages</h3>
                <ul class="attention-list">
                    <?php foreach ($latestMessages as $m): ?>
                        <li>
                            <span class="avatar"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
                            <div class="text"><strong><?= e($m['name']) ?></strong><small><?= e(excerpt($m['message'], 70)) ?></small></div>
                            <a class="btn btn-sm btn-light" href="<?= e(url('admin/contact.php')) ?>">Reply</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4 col-lg-6">
        <div class="panel">
            <div class="panel-head"><h2>Recent activity</h2></div>
            <?php if (!$activity): ?>
                <?php render_empty_state('bi-activity', 'No activity yet', 'Applications, adoptions and sign-ups will show up here.', '', '', true); ?>
            <?php else: ?>
                <ul class="activity">
                    <?php foreach ($activity as [$time, $icon, $tone, $html]): ?>
                        <li><span class="icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span><div class="text"><?= $html ?><small><?= e(time_ago($time)) ?> · <?= e(format_date($time)) ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel">
            <div class="panel-head"><h2>Available by category</h2><a class="panel-link" href="<?= e(url('admin/categories.php')) ?>">Manage <i class="bi bi-arrow-right"></i></a></div>
            <?php if (!$byCategory): ?>
                <?php render_empty_state('bi-tags', 'No categories', 'Add a category to start listing pets.', 'Add category', url('admin/categories.php?add=1'), true); ?>
            <?php else: ?>
                <ul class="bar-list">
                    <?php foreach ($byCategory as $c): ?>
                        <li>
                            <a class="name text-reset" href="<?= e(url('admin/categoryPets.php?cindex=' . (int) $c['category_id'])) ?>"><?= e($c['name']) ?></a>
                            <span class="val"><?= (int) $c['n'] ?></span>
                            <span class="track"><span class="fill d-block" style="width:<?= round((int) $c['n'] / $maxCategory * 100, 1) ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <h3 class="section-label mt-4">Quick actions</h3>
            <div class="quick-actions">
                <a class="quick-action" href="<?= e(url('admin/pets.php?add=1')) ?>"><span class="icon tone-brand"><i class="bi bi-plus-lg"></i></span>Add a pet</a>
                <a class="quick-action" href="<?= e(url('admin/categories.php?add=1')) ?>"><span class="icon tone-accent"><i class="bi bi-tags"></i></span>New category</a>
                <a class="quick-action" href="<?= e(url('admin/locations.php#addBranch')) ?>"><span class="icon tone-info"><i class="bi bi-geo-alt"></i></span>New branch</a>
                <a class="quick-action" href="<?= e(url('admin/contact.php')) ?>"><span class="icon tone-sun"><i class="bi bi-chat-dots"></i></span>Messages</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xxl-8">
        <div class="panel panel-flush">
            <div class="panel-head"><div><h2>By animal type</h2><p>Listings, demand and how fast each kind of pet finds a home</p></div><a class="panel-link" href="<?= e(url('admin/categories.php')) ?>">Categories <i class="bi bi-arrow-right"></i></a></div>
            <div class="table-responsive mt-3">
                <table class="data-table">
                    <thead><tr><th>Animal</th><th class="num">Listed</th><th class="num">Available</th><th class="num">Applications</th><th class="num">Adopted</th><th class="num" title="Average from application to pickup">Avg. to adopt</th><th class="num" title="Average days available pets have been in care">Avg. in care</th></tr></thead>
                    <tbody>
                    <?php foreach ($byType as $t): ?>
                        <tr>
                            <td><a class="fw-bold text-reset" href="<?= e(url('admin/categoryPets.php?cindex=' . (int) $t['category_id'])) ?>"><?= e($t['name']) ?></a></td>
                            <td class="num"><?= (int) $t['listed'] ?></td>
                            <td class="num fw-bold"><?= (int) $t['available'] ?></td>
                            <td class="num"><?= (int) $t['apps'] ?></td>
                            <td class="num"><?= (int) $t['adopted'] ?></td>
                            <td class="num text-nowrap"><?= e(admin_days_label($t['avg_adopt'] === null ? null : (float) $t['avg_adopt'])) ?></td>
                            <td class="num text-nowrap"><?= $t['avg_in_care'] === null ? '—' : (int) round((float) $t['avg_in_care']) . ' d' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byType): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No categories yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="panel">
            <div class="panel-head"><div><h2>Most loved</h2><p>Pets adopters saved most often</p></div></div>
            <?php if (!$mostLoved): ?>
                <?php render_empty_state('bi-heart', 'No saved pets yet', 'When adopters tap the heart on a pet it shows up here.', '', '', true); ?>
            <?php else: ?>
                <ul class="attention-list">
                    <?php foreach ($mostLoved as $p): ?>
                        <li>
                            <img class="thumb" src="<?= e(pet_image_url($p['image'])) ?>" alt=""<?= img_fallback() ?>>
                            <div class="text"><strong><?= e($p['name']) ?></strong>
                                <small><?= e($p['category_name'] ?? '') ?> · <?= (int) $p['apps'] ?> application<?= (int) $p['apps'] === 1 ? '' : 's' ?></small></div>
                            <?php if (!(int) $p['apps'] && !(int) $p['requested']): ?><span class="status-pill status-warning" title="Saved by adopters but nobody has applied yet">No applications</span><?php endif; ?>
                            <span class="save-count"><i class="bi bi-heart-fill"></i><?= (int) $p['saves'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="metric-hint mt-3 mb-0"><i class="bi bi-lightbulb"></i> Pets with saves but no applications may need better photos or a nudge on social media.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="panel panel-flush">
            <div class="panel-head"><h2>Branches</h2><a class="panel-link" href="<?= e(url('admin/locations.php')) ?>">Manage branches <i class="bi bi-arrow-right"></i></a></div>
            <div class="table-responsive mt-3">
                <table class="data-table">
                    <thead><tr><th>Branch</th><th class="num">Available</th><th class="num">Pending</th><th class="num" title="Approved, awaiting pickup">Pickup</th><th class="num">Adopted</th><th class="num" title="Average from application to pickup">Avg. days</th></tr></thead>
                    <tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr>
                            <td><strong><?= e($b['city']) ?></strong><span class="sub"><?= e($b['address']) ?></span></td>
                            <td class="num fw-bold"><?= (int) $b['available'] ?></td>
                            <td class="num"><?= $b['pending'] ? '<span class="status-pill status-warning">' . (int) $b['pending'] . '</span>' : '0' ?></td>
                            <td class="num"><?= $b['accepted'] ? '<span class="status-pill status-info">' . (int) $b['accepted'] . '</span>' : '0' ?></td>
                            <td class="num"><?= (int) $b['adopted'] ?></td>
                            <td class="num text-nowrap"><?= e(admin_days_label($b['avg_adopt'] === null ? null : (float) $b['avg_adopt'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$branches): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No branches yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel panel-flush">
            <div class="panel-head"><h2>Recent registrations</h2><a class="panel-link" href="<?= e(url('admin/adopted.php?tab=adopters')) ?>">All adopters <i class="bi bi-arrow-right"></i></a></div>
            <div class="table-responsive mt-3">
                <table class="data-table compact">
                    <thead><tr><th>Adopter</th><th>Joined</th><th class="num">Apps</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentAdopters as $a): ?>
                        <tr>
                            <td><div class="cell-main"><span class="avatar"><?= e(strtoupper(mb_substr($a['name'], 0, 1))) ?></span><div><strong><?= e($a['name']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                            <td class="text-nowrap"><?= e(time_ago($a['created_at'])) ?></td>
                            <td class="num"><?= (int) $a['apps'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentAdopters): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No adopters have registered yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('activityChart');
    if (!window.Chart || !canvas) return;
    Chart.defaults.font.family = "'Figtree', system-ui, sans-serif";
    Chart.defaults.color = '#64706b';
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [
                { label: 'Applications', data: <?= json_encode($appSeries) ?>, backgroundColor: '#e2622f', hoverBackgroundColor: '#b8461a' },
                { label: 'Adoptions', data: <?= json_encode($adoptSeries) ?>, backgroundColor: '#119174', hoverBackgroundColor: '#0f4f43' }
            ].map(function (d) { return Object.assign(d, { borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', borderColor: '#ffffff', borderWidth: { left: 2, right: 2 }, maxBarThickness: 28, categoryPercentage: .6, barPercentage: .9 }); })
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: '#17211e', padding: 12, cornerRadius: 10, boxPadding: 4, usePointStyle: true, titleFont: { weight: '700' } }
            },
            scales: {
                x: { grid: { display: false }, border: { color: '#d7d0c4' } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#ecede9' }, border: { display: false } }
            }
        }
    });
})();
</script>
<?php page_end(); ?>
