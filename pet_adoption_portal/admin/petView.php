<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$pet = find_pet(query_int('pindex'));
if (!$pet) {
    flash('That pet could not be found. It may have been adopted or deleted.', 'warning');
    redirect('admin/pets.php');
}
$application = (int) $pet['requested'] !== 0;
$favouriteCount = (int) db_value("SELECT COUNT(*) FROM `favourites` WHERE `pet_id` = ?", [(int) $pet['pet_id']]);
$pastApplications = (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `pet_id` = ? AND `status` <> 'pending'", [(int) $pet['pet_id']]);

$pageTitle = $pet['name'];
$activeNav = 'pets';
include __DIR__ . '/header.php';
?>
<div class="admin-page-head">
    <div>
        <a class="back" href="<?= e(url('admin/pets.php')) ?>"><i class="bi bi-arrow-left"></i> All pets</a>
        <h1><?= e($pet['name']) ?></h1>
        <p><?= e(trim(($pet['category_name'] ?? '') . ($pet['breed'] ? ' · ' . $pet['breed'] : ''), ' ·')) ?> · Listed <?= e(format_date($pet['created_at'])) ?></p>
    </div>
    <div class="head-actions">
        <a class="btn btn-light" href="<?= e(url('petInfo.php?pindex=' . (int) $pet['pet_id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Public page</a>
        <a class="btn btn-primary" href="<?= e(url('admin/petEdit.php?pindex=' . (int) $pet['pet_id'])) ?>"><i class="bi bi-pencil"></i> Edit pet</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="panel h-auto p-2">
            <img class="w-100 rounded-4" style="aspect-ratio:1/1;object-fit:cover" src="<?= e(pet_image_url($pet['image'])) ?>" alt="<?= e($pet['name']) ?>"<?= img_fallback() ?>>
        </div>
    </div>
    <div class="col-lg-7 d-flex flex-column gap-3">
        <div class="panel h-auto">
            <div class="panel-head">
                <h2>Profile</h2>
                <?php if ($application): ?>
                    <a class="status-pill status-warning" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $pet['pet_id'])) ?>"><i class="bi bi-hourglass-split"></i>Application in progress — open</a>
                <?php else: ?>
                    <span class="status-pill status-success"><i class="bi bi-check-circle"></i>Available for adoption</span>
                <?php endif; ?>
            </div>
            <div class="chips mb-3"><?= pet_badges($pet) ?></div>
            <h3 class="section-label">Introduction</h3>
            <p class="break-word"><?= nl2br(e($pet['intro1'])) ?></p>
            <?php if ($pet['intro2'] !== ''): ?><h3 class="section-label">Health &amp; home needs</h3><p class="mb-0 break-word"><?= nl2br(e($pet['intro2'])) ?></p><?php endif; ?>
        </div>
        <div class="row g-3">
            <div class="col-sm-6">
                <div class="mini-stat"><span class="icon tone-accent"><i class="bi bi-heart-fill"></i></span><div><strong><?= $favouriteCount ?></strong><span>adopter<?= $favouriteCount === 1 ? ' has' : 's have' ?> saved <?= e(excerpt($pet['name'], 20)) ?></span></div></div>
            </div>
            <div class="col-sm-6">
                <div class="mini-stat"><span class="icon tone-ink"><i class="bi bi-clock-history"></i></span><div><strong><?= $pastApplications ?></strong><span>earlier application<?= $pastApplications === 1 ? '' : 's' ?></span></div></div>
            </div>
        </div>
        <div class="panel h-auto">
            <div class="panel-head"><h2>Branch</h2><a class="panel-link" href="<?= e(url('admin/locationEdit.php?lindex=' . (int) $pet['location_id'])) ?>">Edit branch <i class="bi bi-arrow-right"></i></a></div>
            <div class="row g-2">
                <div class="col-md-4"><div class="info-row"><i class="bi bi-geo-alt"></i><div><small><?= e($pet['city']) ?></small><?= e($pet['location_address']) ?></div></div></div>
                <div class="col-md-4"><div class="info-row"><i class="bi bi-telephone"></i><div><small>Contact</small><?= e($pet['location_contact']) ?></div></div></div>
                <div class="col-md-4"><div class="info-row"><i class="bi bi-envelope"></i><div><small>Email</small><?= e($pet['location_email']) ?></div></div></div>
            </div>
        </div>
    </div>
</div>
<?php page_end(); ?>
