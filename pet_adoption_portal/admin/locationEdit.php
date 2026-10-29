<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$location = db_one("SELECT * FROM `locations` WHERE `location_id` = ?", [query_int('lindex')]);
if (!$location) {
    flash('That branch no longer exists.', 'warning');
    redirect('admin/locations.php');
}
$locationId = (int) $location['location_id'];
$inUse = [
    'pets'             => (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `location_id` = ?", [$locationId]),
    'open requests'    => (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `location_id` = ? AND `status` = 'pending'", [$locationId]),
    'approved pickups' => (int) db_value("SELECT COUNT(*) FROM `accepted` WHERE `location_id` = ?", [$locationId]),
];
$blockers = array_filter($inUse);

if (is_post() && post('action') === 'delete') {
    if ($blockers) {
        flash('This branch still has ' . implode(', ', array_map(fn($n, $k) => "$n $k", $blockers, array_keys($blockers))) . '. Move or finish them first.', 'danger');
        redirect('admin/locationEdit.php?lindex=' . $locationId);
    }
    db_run("DELETE FROM `locations` WHERE `location_id` = ?", [$locationId]);
    flash('The ' . $location['city'] . ' branch was deleted.', 'info');
    redirect('admin/locations.php');
}

$errors = [];
$old = ['city' => $location['city'], 'address' => $location['address'], 'email' => $location['email'], 'contact' => $location['contact']];
if (is_post() && post('action') === 'save') {
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
    if (!$errors) {
        db_run("UPDATE `locations` SET `city` = ?, `address` = ?, `email` = ?, `contact` = ? WHERE `location_id` = ?",
            [$old['city'], $old['address'], $old['email'], $old['contact'], $locationId]);
        flash('Branch details saved.');
        redirect('admin/locations.php');
    }
}

$pageTitle = 'Edit ' . $location['city'];
$activeNav = 'locations';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
?>
<div class="admin-page-head">
    <div>
        <a class="back" href="<?= e(url('admin/locations.php')) ?>"><i class="bi bi-arrow-left"></i> All branches</a>
        <h1><?= e($location['city']) ?> branch</h1>
        <p><?= $inUse['pets'] ?> pet<?= $inUse['pets'] === 1 ? '' : 's' ?> listed here.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form class="panel h-auto" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <div class="panel-head"><h2>Branch details</h2></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control<?= $invalid('city') ?>" id="city" name="city" maxlength="100" value="<?= e($old['city']) ?>" required>
                    <?= $feedback('city') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="contact">Phone</label>
                    <input class="form-control<?= $invalid('contact') ?>" id="contact" name="contact" maxlength="20" value="<?= e($old['contact']) ?>" inputmode="tel" required>
                    <?= $feedback('contact') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Address</label>
                    <input class="form-control<?= $invalid('address') ?>" id="address" name="address" maxlength="255" value="<?= e($old['address']) ?>" required>
                    <?= $feedback('address') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control<?= $invalid('email') ?>" id="email" name="email" type="email" maxlength="100" value="<?= e($old['email']) ?>" required>
                    <?= $feedback('email') ?>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
                <a class="btn btn-link" href="<?= e(url('admin/locations.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <div class="col-lg-5 d-flex flex-column gap-3">
        <div class="row g-3">
            <?php foreach ([['heart', 'tone-accent', $inUse['pets'], 'Pets listed'], ['inbox', 'tone-warning', $inUse['open requests'], 'Open requests'], ['calendar-check', 'tone-info', $inUse['approved pickups'], 'Awaiting pickup']] as [$icon, $tone, $n, $label]): ?>
                <div class="col-4"><div class="mini-stat flex-column align-items-start gap-2 p-3"><span class="icon <?= $tone ?>" style="width:38px;height:38px;font-size:1rem"><i class="bi bi-<?= $icon ?>"></i></span><div><strong><?= $n ?></strong><span><?= $label ?></span></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="panel h-auto danger-zone">
            <h2><i class="bi bi-exclamation-triangle"></i> Delete branch</h2>
            <?php if ($blockers): ?>
                <p class="text-muted small">This branch can’t be deleted while it still has:</p>
                <ul class="small mb-3">
                    <?php foreach ($blockers as $label => $n): ?><li><strong><?= $n ?></strong> <?= e($label) ?></li><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted small">Nothing is linked to this branch, so it can be removed. Adoption history keeps the branch name.</p>
            <?php endif; ?>
            <form method="post" data-confirm="The <?= e($location['city']) ?> branch will be removed from the website." data-confirm-title="Delete branch?" data-confirm-ok="Delete branch">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-soft-danger"<?= $blockers ? ' disabled' : '' ?>><i class="bi bi-trash"></i> Delete branch</button>
            </form>
        </div>
    </div>
</div>
<?php page_end(); ?>
