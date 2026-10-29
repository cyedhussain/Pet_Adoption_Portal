<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$category = db_one("SELECT * FROM `categories` WHERE `category_id` = ?", [query_int('cindex')]);
if (!$category) {
    flash('That category no longer exists.', 'warning');
    redirect('admin/categories.php');
}
$categoryId = (int) $category['category_id'];
$petCount = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `category_id` = ?", [$categoryId]);

if (is_post() && post('action') === 'delete') {
    if ($petCount > 0) {
        flash('Move or delete the ' . $petCount . ' pet' . ($petCount === 1 ? '' : 's') . ' in this category before deleting it.', 'danger');
        redirect('admin/categoryEdit.php?cindex=' . $categoryId);
    }
    db_run("DELETE FROM `categories` WHERE `category_id` = ?", [$categoryId]);
    if (!db_value("SELECT COUNT(*) FROM `categories` WHERE `image` = ?", [$category['image']])) {
        delete_image('category', $category['image']);
    }
    flash('Category “' . $category['name'] . '” was deleted.', 'info');
    redirect('admin/categories.php');
}

$errors = [];
$old = ['name' => $category['name'], 'description' => $category['description']];
if (is_post() && post('action') === 'save') {
    $old = ['name' => post('name'), 'description' => post('description')];
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Enter a name (up to 100 characters).';
    } elseif (db_value("SELECT COUNT(*) FROM `categories` WHERE `name` = ? AND `category_id` <> ?", [$old['name'], $categoryId])) {
        $errors['name'] = 'Another category already uses this name.';
    }
    if ($old['description'] === '' || mb_strlen($old['description']) > 255) {
        $errors['description'] = 'Write a short description (up to 255 characters).';
    }
    $newImage = null;
    if (!$errors) {
        $upload = upload_image('picture', 'category', false);
        if ($upload['error']) {
            $errors['picture'] = $upload['error'];
        }
        $newImage = $upload['file'];
    }
    if (!$errors) {
        db_run("UPDATE `categories` SET `name` = ?, `description` = ?, `image` = ? WHERE `category_id` = ?",
            [$old['name'], $old['description'], $newImage ?? $category['image'], $categoryId]);
        if ($newImage && !db_value("SELECT COUNT(*) FROM `categories` WHERE `image` = ?", [$category['image']])) {
            delete_image('category', $category['image']);
        }
        flash('Category saved.');
        redirect('admin/categories.php');
    }
}

$pageTitle = 'Edit ' . $category['name'];
$activeNav = 'categories';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
?>
<div class="admin-page-head">
    <div>
        <a class="back" href="<?= e(url('admin/categories.php')) ?>"><i class="bi bi-arrow-left"></i> All categories</a>
        <h1>Edit <?= e($category['name']) ?></h1>
        <p><?= $petCount ?> pet<?= $petCount === 1 ? '' : 's' ?> in this category.</p>
    </div>
    <a class="btn btn-light" href="<?= e(url('admin/categoryPets.php?cindex=' . $categoryId)) ?>"><i class="bi bi-collection"></i> View pets</a>
</div>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel h-auto">
                <div class="panel-head"><h2>Details</h2></div>
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" value="<?= e($old['name']) ?>" required>
                    <?= $feedback('name') ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= $invalid('description') ?>" id="description" name="description" rows="4" maxlength="255" data-count="#descCount" required><?= e($old['description']) ?></textarea>
                    <?= $feedback('description') ?>
                    <div class="form-text d-flex justify-content-between"><span>Shown on the category card and page.</span><span class="char-count" id="descCount"></span></div>
                </div>
                <button class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
                <a class="btn btn-link" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel h-auto">
                <div class="panel-head"><h2>Cover image</h2></div>
                <div class="file-drop">
                    <img id="categoryPreview" class="image-preview" src="<?= e(category_image_url($category['image'])) ?>" alt=""<?= img_fallback() ?>>
                    <label class="visually-hidden" for="picture">Replace cover image</label>
                    <input class="form-control<?= $invalid('picture') ?>" type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#categoryPreview">
                    <?= $feedback('picture') ?>
                    <div class="form-text mt-0">Leave empty to keep the current image.</div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="panel h-auto mt-3 danger-zone">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2><i class="bi bi-exclamation-triangle"></i> Delete this category</h2>
            <p class="text-muted small mb-0">
                <?= $petCount
                    ? 'This category still has ' . $petCount . ' pet' . ($petCount === 1 ? '' : 's') . '. Move or delete them first.'
                    : 'The category has no pets and can be removed safely.' ?>
            </p>
        </div>
        <form method="post" data-confirm="The <?= e($category['name']) ?> category will be removed from the website." data-confirm-title="Delete category?" data-confirm-ok="Delete category">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button class="btn btn-soft-danger"<?= $petCount ? ' disabled' : '' ?>><i class="bi bi-trash"></i> Delete category</button>
        </form>
    </div>
</div>
<?php page_end(); ?>
