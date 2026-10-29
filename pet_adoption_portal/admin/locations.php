<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$errors = [];
$old = ['city' => '', 'address' => '', 'email' => '', 'contact' => ''];
if (is_post() && post('action') === 'add_location') {
    foreach (array_keys($old) as $key) {
        $old[$key] = post($key);
    }
    if ($old['city'] === '' || mb_strlen($old['city']) > 100) {
        $errors['city'] = 'Enter the city (up to 100 characters).';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors['address'] = 'Enter the street address.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 100) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9+()\-\s]{6,20}$/', $old['contact'])) {
        $errors['contact'] = 'Enter a phone number (digits, spaces, + - and brackets).';
    }
    if (!$errors && db_value("SELECT COUNT(*) FROM `locations` WHERE `city` = ? AND `address` = ?", [$old['city'], $old['address']])) {
        $errors['address'] = 'This branch already exists.';
    }
    if (!$errors) {
        db_run("INSERT INTO `locations` (`city`, `address`, `email`, `contact`) VALUES (?, ?, ?, ?)",
            [$old['city'], $old['address'], $old['email'], $old['contact']]);
        flash('The ' . $old['city'] . ' branch was added.');
        redirect('admin/locations.php');
    }
}

$locations = db_all(
    "SELECT l.*,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`location_id` = l.`location_id`) AS pets,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`location_id` = l.`location_id` AND p.`requested` = 0) AS available,
        (SELECT COUNT(*) FROM `adopted` a WHERE a.`location_city` = l.`city`) AS adopted,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`location_id` = l.`location_id` AND r.`status` = 'pending') AS pending,
        (SELECT COUNT(*) FROM `accepted` c WHERE c.`location_id` = l.`location_id`) AS pickup,
        (SELECT COUNT(*) FROM `accepted` c WHERE c.`location_id` = l.`location_id` AND c.`accept_time` < NOW() - INTERVAL 3 DAY) AS overdue,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, a.`request_time`, a.`adopt_time`) / 1440) FROM `adopted` a WHERE a.`location_city` = l.`city`) AS avg_adopt,
        (SELECT AVG(DATEDIFF(NOW(), p.`created_at`)) FROM `pets` p WHERE p.`location_id` = l.`location_id` AND p.`requested` = 0) AS avg_care
     FROM `locations` l ORDER BY l.`city`"
);

$pageTitle = 'Branches';
$activeNav = 'locations';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
?>
<div class="admin-page-head">
    <div>
        <h1>Branches</h1>
        <p>Shelter locations where pets live and adopters pick them up. <?= count($locations) ?> branch<?= count($locations) === 1 ? '' : 'es' ?>.</p>
    </div>
    <a class="btn btn-primary d-xl-none" href="#addBranch"><i class="bi bi-plus-lg"></i> Add branch</a>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <?php if ($locations): ?>
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Branch</th><th>Contact</th><th class="num">Available / listed</th><th class="num">In progress</th><th class="num">Adopted</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($locations as $l): ?>
                            <tr>
                                <td>
                                    <div class="cell-main">
                                        <span class="d-grid rounded-3 tone-brand flex-none" style="width:44px;height:44px;place-items:center"><i class="bi bi-geo-alt-fill"></i></span>
                                        <div><strong><?= e($l['city']) ?></strong><small><?= e($l['address']) ?></small></div>
                                    </div>
                                </td>
                                <td><a class="d-block text-nowrap" href="tel:<?= e($l['contact']) ?>"><?= e($l['contact']) ?></a><span class="sub"><?= e($l['email']) ?></span></td>
                                <td class="num text-nowrap"><strong><?= (int) $l['available'] ?></strong><span class="text-muted"> / <?= (int) $l['pets'] ?></span><span class="sub" title="Average days available pets have been in care"><?= $l['avg_care'] === null ? '—' : (int) round((float) $l['avg_care']) . ' d in care' ?></span></td>
                                <td class="num">
                                    <?= (int) $l['pending'] ? '<span class="status-pill status-warning" title="Pending requests">' . (int) $l['pending'] . ' pending</span>' : '' ?>
                                    <?= (int) $l['pickup'] ? '<span class="status-pill ' . ((int) $l['overdue'] ? 'status-danger' : 'status-info') . '" title="Approved, awaiting pickup' . ((int) $l['overdue'] ? ' (' . (int) $l['overdue'] . ' overdue)' : '') . '">' . (int) $l['pickup'] . ' pickup</span>' : '' ?>
                                    <?= !(int) $l['pending'] && !(int) $l['pickup'] ? '<span class="text-muted">0</span>' : '' ?>
                                </td>
                                <?php $avg = $l['avg_adopt'] === null ? null : (float) $l['avg_adopt']; ?>
                                <td class="num text-nowrap"><strong><?= (int) $l['adopted'] ?></strong><span class="sub" title="Average from application to pickup"><?= $avg === null ? '—' : ($avg < 1 ? max(1, (int) round($avg * 24)) . ' h' : round($avg, 1) . ' d') . ' avg' ?></span></td>
                                <td class="text-end"><a class="btn btn-icon btn-sm btn-light" href="<?= e(url('admin/locationEdit.php?lindex=' . (int) $l['location_id'])) ?>" title="Edit <?= e($l['city']) ?>" aria-label="Edit <?= e($l['city']) ?>"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <?php render_empty_state('bi-geo-alt', 'No branches yet', 'Add your first shelter location using the form.'); ?>
        <?php endif; ?>
    </div>
    <div class="col-xl-3">
        <form class="panel h-auto" method="post" novalidate id="addBranch">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_location">
            <div class="panel-head"><div><h2>Add a branch</h2><p>It appears on the website straight away.</p></div></div>
            <?php if ($errors): ?>
                <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="city">City</label>
                <input class="form-control<?= $invalid('city') ?>" id="city" name="city" maxlength="100" value="<?= e($old['city']) ?>" required>
                <?= $feedback('city') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="address">Address</label>
                <input class="form-control<?= $invalid('address') ?>" id="address" name="address" maxlength="255" value="<?= e($old['address']) ?>" required>
                <?= $feedback('address') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control<?= $invalid('email') ?>" id="email" name="email" type="email" maxlength="100" value="<?= e($old['email']) ?>" required>
                <?= $feedback('email') ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="contact">Phone</label>
                <input class="form-control<?= $invalid('contact') ?>" id="contact" name="contact" maxlength="20" value="<?= e($old['contact']) ?>" placeholder="0543-1234567" inputmode="tel" required>
                <?= $feedback('contact') ?>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add branch</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
