<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

/** True while a pet or adopted row still points at this image in img/pets/. */
$pet = db_one("SELECT * FROM `pets` WHERE `pet_id` = ?", [query_int('pindex')]);
if (!$pet) {
    flash('That pet could not be found. It may have been adopted or deleted.', 'warning');
    redirect('admin/pets.php');
}
$petId = (int) $pet['pet_id'];
$categories = all_categories();
$locations = all_locations();

$errors = [];
$old = [
    'name' => $pet['name'], 'category_id' => $pet['category_id'], 'location_id' => $pet['location_id'],
    'breed' => $pet['breed'], 'age' => $pet['age'] === null ? '' : (string) $pet['age'], 'gender' => $pet['gender'],
    'size' => $pet['size'], 'vaccinated' => $pet['vaccinated'] ? '1' : '', 'intro1' => $pet['intro1'], 'intro2' => $pet['intro2'],
];

if (is_post() && post('action') === 'delete') {
    if ((int) $pet['requested'] !== 0) {
        flash($pet['name'] . ' has an application in progress. Reject it or finish the adoption before deleting.', 'danger');
        redirect('admin/petEdit.php?pindex=' . $petId);
    }
    db_transaction(function () use ($petId) {
        db_run("DELETE FROM `favourites` WHERE `pet_id` = ?", [$petId]);
        db_run("DELETE FROM `pets` WHERE `pet_id` = ?", [$petId]);
    });
    if (!pet_image_in_use($pet['image'])) {
        delete_image('pets', $pet['image']);
    }
    flash($pet['name'] . ' was deleted.', 'info');
    redirect('admin/pets.php');
}

if (is_post() && post('action') === 'save') {
    foreach (array_keys($old) as $key) {
        $old[$key] = post($key);
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 50) {
        $errors['name'] = 'Enter a name (up to 50 characters).';
    }
    if (!db_value("SELECT COUNT(*) FROM `categories` WHERE `category_id` = ?", [(int) $old['category_id']])) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (!db_value("SELECT COUNT(*) FROM `locations` WHERE `location_id` = ?", [(int) $old['location_id']])) {
        $errors['location_id'] = 'Choose a branch.';
    }
    if (mb_strlen($old['breed']) > 100) {
        $errors['breed'] = 'Keep the breed under 100 characters.';
    }
    if ($old['age'] !== '' && (!ctype_digit($old['age']) || (int) $old['age'] > 40)) {
        $errors['age'] = 'Age must be a whole number of years (0 for under 1).';
    }
    if ($old['gender'] !== '' && !in_array($old['gender'], PET_GENDERS, true)) {
        $errors['gender'] = 'Choose a gender.';
    }
    if ($old['size'] !== '' && !in_array($old['size'], PET_SIZES, true)) {
        $errors['size'] = 'Choose a size.';
    }
    if ($old['intro1'] === '') {
        $errors['intro1'] = 'Write a short introduction.';
    }
    $newImage = null;
    if (!$errors) {
        $upload = upload_image('picture', 'pets', false);
        if ($upload['error']) {
            $errors['picture'] = $upload['error'];
        }
        $newImage = $upload['file'];
    }
    if (!$errors) {
        db_run(
            "UPDATE `pets` SET `category_id` = ?, `name` = ?, `intro1` = ?, `intro2` = ?, `image` = ?, `location_id` = ?,
                `breed` = ?, `age` = ?, `gender` = ?, `size` = ?, `vaccinated` = ?
             WHERE `pet_id` = ?",
            [(int) $old['category_id'], $old['name'], $old['intro1'], $old['intro2'], $newImage ?? $pet['image'], (int) $old['location_id'],
             $old['breed'], $old['age'] === '' ? null : (int) $old['age'], $old['gender'], $old['size'], $old['vaccinated'] === '1' ? 1 : 0, $petId]
        );
        if ($newImage && !pet_image_in_use($pet['image'])) {
            delete_image('pets', $pet['image']);
        }
        flash('Changes to ' . $old['name'] . ' were saved.');
        redirect('admin/petView.php?pindex=' . $petId);
    }
}

$pageTitle = 'Edit ' . $pet['name'];
$activeNav = 'pets';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
$sel = fn($a, $b) => (string) $a === (string) $b ? ' selected' : '';
?>
<div class="admin-page-head">
    <div>
        <a class="back" href="<?= e(url('admin/petView.php?pindex=' . $petId)) ?>"><i class="bi bi-arrow-left"></i> Back to <?= e(excerpt($pet['name'], 30)) ?></a>
        <h1>Edit <?= e($pet['name']) ?></h1>
        <p>Update the profile adopters see on the website.</p>
    </div>
</div>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel h-auto">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
                <?php endif; ?>
                <div class="form-section">
                    <h2 class="form-section-title"><span class="n">1</span> Basics</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name</label>
                            <input class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="50" value="<?= e($old['name']) ?>" required>
                            <?= $feedback('name') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="breed">Breed</label>
                            <input class="form-control<?= $invalid('breed') ?>" id="breed" name="breed" maxlength="100" value="<?= e($old['breed']) ?>">
                            <?= $feedback('breed') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select<?= $invalid('category_id') ?>" id="category_id" name="category_id">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['category_id'] ?>"<?= $sel($old['category_id'], $c['category_id']) ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $feedback('category_id') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="location_id">Branch</label>
                            <select class="form-select<?= $invalid('location_id') ?>" id="location_id" name="location_id">
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int) $l['location_id'] ?>"<?= $sel($old['location_id'], $l['location_id']) ?>><?= e($l['city'] . ' · ' . $l['address']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $feedback('location_id') ?>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h2 class="form-section-title"><span class="n">2</span> Details</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="age">Age (years)</label>
                            <input class="form-control<?= $invalid('age') ?>" id="age" name="age" type="number" min="0" max="40" value="<?= e($old['age']) ?>" placeholder="0 = under 1">
                            <?= $feedback('age') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="gender">Gender</label>
                            <select class="form-select<?= $invalid('gender') ?>" id="gender" name="gender">
                                <option value="">Unknown</option>
                                <?php foreach (PET_GENDERS as $g): ?><option<?= $sel($old['gender'], $g) ?>><?= $g ?></option><?php endforeach; ?>
                            </select>
                            <?= $feedback('gender') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="size">Size</label>
                            <select class="form-select<?= $invalid('size') ?>" id="size" name="size">
                                <option value="">Unknown</option>
                                <?php foreach (PET_SIZES as $s): ?><option<?= $sel($old['size'], $s) ?>><?= $s ?></option><?php endforeach; ?>
                            </select>
                            <?= $feedback('size') ?>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="vaccinated" name="vaccinated" value="1"<?= $old['vaccinated'] === '1' ? ' checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="vaccinated">Fully vaccinated</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h2 class="form-section-title"><span class="n">3</span> Profile text</h2>
                    <div class="mb-3">
                        <label class="form-label" for="intro1">Introduction</label>
                        <textarea class="form-control<?= $invalid('intro1') ?>" id="intro1" name="intro1" rows="4" required><?= e($old['intro1']) ?></textarea>
                        <?= $feedback('intro1') ?>
                    </div>
                    <label class="form-label" for="intro2">Health &amp; home needs</label>
                    <textarea class="form-control" id="intro2" name="intro2" rows="3"><?= e($old['intro2']) ?></textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="d-flex flex-column gap-3" style="position:sticky;top:88px">
                <div class="panel h-auto">
                    <div class="panel-head"><h2>Photo</h2></div>
                    <div class="file-drop">
                        <img id="petPreview" class="image-preview" src="<?= e(pet_image_url($pet['image'])) ?>" alt="<?= e($pet['name']) ?>"<?= img_fallback() ?>>
                        <label class="visually-hidden" for="picture">Replace photo</label>
                        <input class="form-control<?= $invalid('picture') ?>" type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#petPreview">
                        <?= $feedback('picture') ?>
                        <div class="form-text mt-0">Leave empty to keep the current photo.</div>
                    </div>
                </div>
                <div class="panel h-auto">
                    <button class="btn btn-primary w-100"><i class="bi bi-check2"></i> Save changes</button>
                    <a class="btn btn-link w-100 mt-1" href="<?= e(url('admin/petView.php?pindex=' . $petId)) ?>">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="panel h-auto mt-3 danger-zone">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2><i class="bi bi-exclamation-triangle"></i> Delete this pet</h2>
            <p class="text-muted small mb-0">
                <?= (int) $pet['requested'] !== 0
                    ? e($pet['name']) . ' has an application in progress, so it can’t be deleted right now.'
                    : 'Removes ' . e($pet['name']) . ' from the website and from adopters’ saved lists. This can’t be undone.' ?>
            </p>
        </div>
        <form method="post" data-confirm="<?= e($pet['name']) ?> will be removed from the website and from adopters’ saved lists. This can’t be undone." data-confirm-title="Delete <?= e(excerpt($pet['name'], 24)) ?>?" data-confirm-ok="Delete pet">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-soft-danger"<?= (int) $pet['requested'] !== 0 ? ' disabled' : '' ?>><i class="bi bi-trash"></i> Delete pet</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
