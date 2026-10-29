<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$errors = [];
$old = ['name' => '', 'description' => ''];
if (is_post() && post('action') === 'add_category') {
    $old = ['name' => post('name'), 'description' => post('description')];
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Enter a name (up to 100 characters).';
    } elseif (db_value("SELECT COUNT(*) FROM `categories` WHERE `name` = ?", [$old['name']])) {
        $errors['name'] = 'A category with this name already exists.';
    }
    if ($old['description'] === '' || mb_strlen($old['description']) > 255) {
        $errors['description'] = 'Write a short description (up to 255 characters).';
    }
    if (!$errors) {
        $upload = upload_image('picture', 'category');
        if ($upload['error']) {
            $errors['picture'] = $upload['error'];
        }
    }
    if (!$errors) {
        db_run("INSERT INTO `categories` (`name`, `description`, `image`) VALUES (?, ?, ?)",
            [$old['name'], $old['description'], $upload['file']]);
        flash('Category “' . $old['name'] . '” was added.');
        redirect('admin/categories.php');
    }
}

$categories = db_all(
    "SELECT c.*,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id`) AS total,
        (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS available,
        (SELECT COUNT(*) FROM `adopted` a WHERE a.`pet_category` = c.`name`) AS adopted,
        (SELECT AVG(TIMESTAMPDIFF(MINUTE, a.`request_time`, a.`adopt_time`) / 1440) FROM `adopted` a WHERE a.`pet_category` = c.`name`) AS avg_adopt,
        (SELECT AVG(DATEDIFF(NOW(), p.`created_at`)) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS avg_care,
        (SELECT COUNT(*) FROM `favourites` f JOIN `pets` p ON p.`pet_id` = f.`pet_id` WHERE p.`category_id` = c.`category_id`) AS saves
     FROM `categories` c ORDER BY c.`name`"
);

$pageTitle = 'Categories';
$activeNav = 'categories';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
?>
<div class="admin-page-head">
    <div>
        <h1>Categories</h1>
        <p>The kinds of animals adopters can browse by. Each category has its own page on the website.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="bi bi-plus-lg"></i> Add category</button>
</div>

<?php if ($categories): ?>
    <div class="row g-3">
        <?php foreach ($categories as $c): ?>
            <div class="col-sm-6 col-xl-4">
                <article class="cat-admin-card">
                    <div class="cover">
                        <img src="<?= e(category_image_url($c['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                        <span class="pet-badge"><?= (int) $c['available'] ?> available</span>
                    </div>
                    <div class="body">
                        <h2><?= e($c['name']) ?></h2>
                        <p class="text-muted small flex-grow-1 mb-0 break-word"><?= e($c['description']) ?></p>
                        <div class="stats">
                            <div><strong><?= (int) $c['available'] ?></strong><span>Available</span></div>
                            <div><strong><?= (int) $c['total'] ?></strong><span>Listed</span></div>
                            <div><strong><?= (int) $c['adopted'] ?></strong><span>Adopted</span></div>
                        </div>
                        <?php $inProgress = (int) $c['total'] - (int) $c['available']; $avgAdopt = $c['avg_adopt'] === null ? null : (float) $c['avg_adopt']; ?>
                        <ul class="cat-insights">
                            <li><i class="bi bi-hourglass-split"></i><span>In progress</span><strong><?= $inProgress ?></strong></li>
                            <li><i class="bi bi-stopwatch"></i><span>Avg. application to home</span><strong><?= $avgAdopt === null ? '—' : ($avgAdopt < 1 ? max(1, (int) round($avgAdopt * 24)) . ' h' : round($avgAdopt, 1) . ' d') ?></strong></li>
                            <li><i class="bi bi-calendar3"></i><span>Avg. days in care (available)</span><strong><?= $c['avg_care'] === null ? '—' : (int) round((float) $c['avg_care']) . ' d' ?></strong></li>
                            <li><i class="bi bi-heart"></i><span>Times saved by adopters</span><strong><?= (int) $c['saves'] ?></strong></li>
                        </ul>
                        <div class="d-flex gap-2">
                            <a class="btn btn-primary btn-sm flex-grow-1" href="<?= e(url('admin/categoryPets.php?cindex=' . (int) $c['category_id'])) ?>"><i class="bi bi-collection"></i> View pets</a>
                            <a class="btn btn-light btn-sm" href="<?= e(url('admin/categoryEdit.php?cindex=' . (int) $c['category_id'])) ?>"><i class="bi bi-pencil"></i> Edit</a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php render_empty_state('bi-tags', 'No categories yet', 'Add a category such as Dogs or Cats to start listing pets.'); ?>
<?php endif; ?>

<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            <div class="modal-header">
                <h2 class="modal-title h5" id="addCategoryLabel">Add a category</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="100" value="<?= e($old['name']) ?>" placeholder="e.g. Birds" required>
                    <?= $feedback('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control<?= $invalid('description') ?>" id="description" name="description" rows="3" maxlength="255" data-count="#descCount" required><?= e($old['description']) ?></textarea>
                    <?= $feedback('description') ?>
                    <div class="form-text text-end char-count" id="descCount"></div>
                </div>
                <label class="form-label" for="picture">Cover image</label>
                <div class="file-drop">
                    <img id="categoryPreview" class="image-preview d-none" alt="Preview of the selected image">
                    <input class="form-control<?= $invalid('picture') ?>" type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#categoryPreview" required>
                    <?= $feedback('picture') ?>
                    <div class="form-text mt-0">JPG, PNG, WEBP or GIF up to 5 MB.<?= $errors && !isset($errors['picture']) ? ' Please choose the image again.' : '' ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add category</button>
            </div>
        </form>
    </div>
</div>
<?php if ($errors || query_param('add') === '1'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addCategoryModal')).show();
    });
</script>
<?php endif; ?>
<?php page_end(); ?>
