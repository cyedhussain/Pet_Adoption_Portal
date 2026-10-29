<?php
/*
 * All pets, with filters and the "Add pet" modal. categoryPets.php reuses this
 * page with $lockedCategory set to show and add pets for a single category.
 */
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

// sort key => [label, ORDER BY]
const PET_ADMIN_SORTS = [
    'newest'  => ['Newest listing', 'p.`created_at` DESC, p.`pet_id` DESC'],
    'longest' => ['Longest in care', 'p.`created_at` ASC, p.`pet_id` ASC'],
    'saves'   => ['Most saved', 'saves DESC, p.`created_at` ASC'],
    'apps'    => ['Most applications', 'apps DESC, p.`created_at` ASC'],
    'name'    => ['Name A–Z', 'p.`name` ASC'],
];

$lockedCategory = $lockedCategory ?? null;
$categories = all_categories();
$locations = all_locations();

$filters = [
    'q'        => query_param('q'),
    'category' => $lockedCategory ? (int) $lockedCategory['category_id'] : query_int('category'),
    'location' => query_int('location'),
    'status'   => in_array(query_param('status'), ['available', 'requested'], true) ? query_param('status') : '',
    'sort'     => array_key_exists(query_param('sort'), PET_ADMIN_SORTS) ? query_param('sort') : 'newest',
];

// ---------------------------------------------------------------- add pet

$errors = [];
$old = ['name' => '', 'category_id' => $filters['category'] ?: '', 'location_id' => '', 'breed' => '', 'age' => '',
        'gender' => '', 'size' => '', 'vaccinated' => '', 'intro1' => '', 'intro2' => ''];

if (is_post() && post('action') === 'add_pet') {
    foreach (array_keys($old) as $key) {
        $old[$key] = post($key);
    }
    if ($lockedCategory) {
        $old['category_id'] = (string) $lockedCategory['category_id'];
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
    if (!$errors) {
        $upload = upload_image('picture', 'pets');
        if ($upload['error']) {
            $errors['picture'] = $upload['error'];
        }
    }
    if (!$errors) {
        db_run(
            "INSERT INTO `pets` (`category_id`, `name`, `intro1`, `intro2`, `image`, `location_id`, `requested`,
                `breed`, `age`, `gender`, `size`, `vaccinated`)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)",
            [(int) $old['category_id'], $old['name'], $old['intro1'], $old['intro2'], $upload['file'], (int) $old['location_id'],
             $old['breed'], $old['age'] === '' ? null : (int) $old['age'], $old['gender'], $old['size'], $old['vaccinated'] === '1' ? 1 : 0]
        );
        flash($old['name'] . ' was added and is now listed for adoption.');
        redirect(current_url_with(['add' => null]));
    }
}

// ---------------------------------------------------------------- list

$where = ['1 = 1'];
$params = [];
if ($filters['q'] !== '') {
    $where[] = '(p.`name` LIKE ? OR p.`breed` LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like);
}
if ($filters['category']) {
    $where[] = 'p.`category_id` = ?';
    $params[] = $filters['category'];
}
if ($filters['location']) {
    $where[] = 'p.`location_id` = ?';
    $params[] = $filters['location'];
}
if ($filters['status'] !== '') {
    $where[] = 'p.`requested` ' . ($filters['status'] === 'available' ? '= 0' : '<> 0');
}
// saves = adopters who hearted the pet; apps = every application ever made for it (any outcome).
$pets = db_all(
    "SELECT p.*, c.`name` AS category_name, l.`city`,
        DATEDIFF(NOW(), p.`created_at`) AS days_in_care,
        (SELECT COUNT(*) FROM `favourites` f WHERE f.`pet_id` = p.`pet_id`) AS saves,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`pet_id` = p.`pet_id`)
        + (SELECT COUNT(*) FROM `accepted` a WHERE a.`pet_id` = p.`pet_id`) AS apps,
        (SELECT COUNT(*) FROM `requests` r WHERE r.`pet_id` = p.`pet_id` AND r.`status` = 'rejected') AS rejected
     FROM `pets` p
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     LEFT JOIN `locations` l ON l.`location_id` = p.`location_id`
     WHERE " . implode(' AND ', $where) . " ORDER BY " . PET_ADMIN_SORTS[$filters['sort']][1],
    $params
);
// Counts for the status tabs use every filter except status.
$countWhere = array_values(array_filter($where, fn($w) => !str_starts_with($w, 'p.`requested`')));
$statusCounts = ['' => 0, 'available' => 0, 'requested' => 0];
foreach (db_all(
    "SELECT p.`requested` <> 0 AS req, COUNT(*) AS n FROM `pets` p
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     WHERE " . implode(' AND ', $countWhere) . " GROUP BY req", $params) as $row) {
    $statusCounts[$row['req'] ? 'requested' : 'available'] = (int) $row['n'];
}
$statusCounts[''] = $statusCounts['available'] + $statusCounts['requested'];
// Summary strip: same scope as the status tabs (every filter except status).
$scope = db_one(
    "SELECT AVG(CASE WHEN p.`requested` = 0 THEN DATEDIFF(NOW(), p.`created_at`) END) AS avg_care,
        MAX(CASE WHEN p.`requested` = 0 THEN DATEDIFF(NOW(), p.`created_at`) END) AS max_care,
        SUM(p.`requested` = 0 AND p.`created_at` < NOW() - INTERVAL 30 DAY
            AND NOT EXISTS (SELECT 1 FROM `requests` r WHERE r.`pet_id` = p.`pet_id` AND r.`request_time` >= NOW() - INTERVAL 30 DAY)) AS stale,
        SUM((SELECT COUNT(*) FROM `favourites` f WHERE f.`pet_id` = p.`pet_id`)) AS saves
     FROM `pets` p
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     WHERE " . implode(' AND ', $countWhere), $params);
[$pagePets, $page, $pages, $total] = paginate($pets, 15);
$filtered = $filters['q'] !== '' || $filters['location'] || $filters['status'] !== '' || (!$lockedCategory && $filters['category']);
$openModal = $errors || query_param('add') === '1';
$selfUrl = $lockedCategory ? url('admin/categoryPets.php?cindex=' . (int) $lockedCategory['category_id']) : url('admin/pets.php');

$pageTitle = $lockedCategory ? $lockedCategory['name'] : 'Pets';
$activeNav = $lockedCategory ? 'categories' : 'pets';
include __DIR__ . '/header.php';

$invalid = fn($key) => isset($errors[$key]) ? ' is-invalid' : '';
$feedback = fn($key) => isset($errors[$key]) ? '<div class="invalid-feedback">' . e($errors[$key]) . '</div>' : '';
$sel = fn($a, $b) => (string) $a === (string) $b ? ' selected' : '';
?>
<div class="admin-page-head">
    <div>
        <?php if ($lockedCategory): ?>
            <a class="back" href="<?= e(url('admin/categories.php')) ?>"><i class="bi bi-arrow-left"></i> All categories</a>
            <h1><?= e($lockedCategory['name']) ?></h1>
            <p><?= e($lockedCategory['description']) ?></p>
        <?php else: ?>
            <h1>Pets</h1>
            <p>Every pet in your shelters, available or with an application in progress.</p>
        <?php endif; ?>
    </div>
    <div class="head-actions">
        <?php if ($lockedCategory): ?>
            <a class="btn btn-light" href="<?= e(url('admin/categoryEdit.php?cindex=' . (int) $lockedCategory['category_id'])) ?>"><i class="bi bi-pencil"></i> Edit category</a>
        <?php endif; ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPetModal"><i class="bi bi-plus-lg"></i> Add pet</button>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <nav class="seg-tabs" aria-label="Status">
        <?php foreach (['' => 'All pets', 'available' => 'Available', 'requested' => 'In progress'] as $key => $label): ?>
            <a class="<?= $filters['status'] === $key ? 'active' : '' ?>" href="<?= e(current_url_with(['status' => $key === '' ? null : $key, 'page' => null])) ?>"<?= $filters['status'] === $key ? ' aria-current="page"' : '' ?>><?= $label ?> <span class="n"><?= $statusCounts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="admin-strip">
    <div><span class="lbl">Available</span><strong><?= $statusCounts['available'] ?></strong><small>ready to meet adopters</small></div>
    <div><span class="lbl">In progress</span><strong><?= $statusCounts['requested'] ?></strong><small>pending or awaiting pickup</small></div>
    <div><span class="lbl">Avg. days in care</span><strong><?= $scope['avg_care'] === null ? '—' : (int) round((float) $scope['avg_care']) ?></strong><small><?= $scope['max_care'] === null ? 'no available pets' : 'longest ' . (int) $scope['max_care'] . ' day' . ((int) $scope['max_care'] === 1 ? '' : 's') ?></small></div>
    <div class="<?= (int) $scope['stale'] ? 'is-warn' : '' ?>"><span class="lbl">No interest in 30 days</span><strong><?= (int) $scope['stale'] ?></strong><small>listed 30+ days, no new application</small></div>
    <div><span class="lbl">Times saved</span><strong><?= (int) $scope['saves'] ?></strong><small>hearts from adopters</small></div>
</div>

<form class="toolbar" method="get" action="<?= e(strtok($selfUrl, '?')) ?>">
    <?php if ($lockedCategory): ?><input type="hidden" name="cindex" value="<?= (int) $lockedCategory['category_id'] ?>"><?php endif; ?>
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <div class="search"><i class="bi bi-search" aria-hidden="true"></i>
        <label class="visually-hidden" for="petQ">Search name or breed</label>
        <input class="form-control" type="search" id="petQ" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name or breed…">
    </div>
    <?php if (!$lockedCategory): ?>
        <select class="form-select" name="category" onchange="this.form.submit()" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['category_id'] ?>"<?= $sel($filters['category'], $c['category_id']) ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <select class="form-select" name="location" onchange="this.form.submit()" aria-label="Branch">
        <option value="">All branches</option>
        <?php foreach ($locations as $l): ?>
            <option value="<?= (int) $l['location_id'] ?>"<?= $sel($filters['location'], $l['location_id']) ?>><?= e($l['city']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="sort" onchange="this.form.submit()" aria-label="Sort">
        <?php foreach (PET_ADMIN_SORTS as $key => [$label]): ?>
            <option value="<?= $key ?>"<?= $sel($filters['sort'], $key) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-dark">Search</button>
    <?php if ($filtered): ?><a class="btn btn-link" href="<?= e($selfUrl) ?>"><i class="bi bi-x-lg"></i> Clear</a><?php endif; ?>
</form>

<?php if ($pets): ?>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Pet</th><th>Category</th><th>Branch</th><th>Details</th><th>Status</th><th>In care</th><th>Interest</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($pagePets as $p): ?>
                    <tr>
                        <td>
                            <a class="cell-main text-reset" href="<?= e(url('admin/petView.php?pindex=' . (int) $p['pet_id'])) ?>">
                                <img class="thumb" src="<?= e(pet_image_url($p['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                                <div><strong><?= e($p['name']) ?></strong><small><?= e($p['breed'] ?: 'Breed not set') ?></small></div>
                            </a>
                        </td>
                        <td><?= e($p['category_name'] ?? '—') ?></td>
                        <td class="text-nowrap"><i class="bi bi-geo-alt text-muted"></i> <?= e($p['city'] ?? '—') ?></td>
                        <td class="text-nowrap"><?= e(implode(' · ', array_filter([$p['gender'], pet_age_label($p['age'])]))) ?>
                            <span class="sub"><?= e($p['size'] ?: 'Size not set') ?> · <?= $p['vaccinated'] ? '<i class="bi bi-shield-check text-success"></i> Vaccinated' : 'Not vaccinated' ?></span></td>
                        <td>
                            <?php if ((int) $p['requested'] === 0): ?>
                                <span class="status-pill status-success"><i class="bi bi-check-circle"></i>Available</span>
                            <?php else: ?>
                                <a class="status-pill status-warning" href="<?= e(url('admin/requestResponse.php?pindex=' . (int) $p['pet_id'])) ?>"><i class="bi bi-hourglass-split"></i>In progress</a>
                            <?php endif; ?>
                        </td>
                        <?php $care = (int) $p['days_in_care']; ?>
                        <td class="text-nowrap"><strong class="<?= (int) $p['requested'] === 0 && $care > 60 ? 'text-danger' : ((int) $p['requested'] === 0 && $care > 30 ? 'text-warning-emphasis' : '') ?>"><?= $care ?> day<?= $care === 1 ? '' : 's' ?></strong><span class="sub">since <?= e(format_date($p['created_at'])) ?></span></td>
                        <td class="text-nowrap">
                            <span class="interest" title="Saved by <?= (int) $p['saves'] ?> adopter<?= (int) $p['saves'] === 1 ? '' : 's' ?>"><i class="bi bi-heart<?= (int) $p['saves'] ? '-fill' : '' ?>"></i><?= (int) $p['saves'] ?></span>
                            <span class="interest" title="<?= (int) $p['apps'] ?> application<?= (int) $p['apps'] === 1 ? '' : 's' ?> in total"><i class="bi bi-file-earmark-text"></i><?= (int) $p['apps'] ?></span>
                            <?php if ((int) $p['rejected']): ?><span class="sub"><?= (int) $p['rejected'] ?> not approved</span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a class="btn btn-icon btn-sm btn-light" href="<?= e(url('admin/petView.php?pindex=' . (int) $p['pet_id'])) ?>" title="View <?= e($p['name']) ?>" aria-label="View <?= e($p['name']) ?>"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-icon btn-sm btn-light" href="<?= e(url('admin/petEdit.php?pindex=' . (int) $p['pet_id'])) ?>" title="Edit <?= e($p['name']) ?>" aria-label="Edit <?= e($p['name']) ?>"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><?php render_pagination($page, $pages, $total, 15, 'pets'); ?></div>
    </div>
<?php elseif ($filtered): ?>
    <?php render_empty_state('bi-search', 'No pets match', 'Try a different search or filter.', 'Clear filters', $selfUrl); ?>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon" aria-hidden="true"><i class="bi bi-heart"></i></div>
        <h3>No pets here yet</h3>
        <p>Add the first pet<?= $lockedCategory ? ' in ' . e($lockedCategory['name']) : '' ?> to list it for adoption.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPetModal"><i class="bi bi-plus-lg"></i> Add pet</button>
    </div>
<?php endif; ?>

<div class="modal fade" id="addPetModal" tabindex="-1" aria-labelledby="addPetLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_pet">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="addPetLabel">Add a pet<?= $lockedCategory ? ' to ' . e($lockedCategory['name']) : '' ?></h2>
                    <small class="text-muted">The pet is listed on the website as soon as you save.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
                <?php endif; ?>
                <div>
                        <div class="form-section">
                            <h3 class="form-section-title"><span class="n">1</span> Basics</h3>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="name">Name</label>
                                    <input class="form-control<?= $invalid('name') ?>" id="name" name="name" maxlength="50" value="<?= e($old['name']) ?>" required>
                                    <?= $feedback('name') ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="breed">Breed</label>
                                    <input class="form-control<?= $invalid('breed') ?>" id="breed" name="breed" maxlength="100" value="<?= e($old['breed']) ?>" placeholder="e.g. Labrador mix">
                                    <?= $feedback('breed') ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="category_id">Category</label>
                                    <?php if ($lockedCategory): ?>
                                        <input class="form-control" id="category_id" value="<?= e($lockedCategory['name']) ?>" readonly>
                                        <input type="hidden" name="category_id" value="<?= (int) $lockedCategory['category_id'] ?>">
                                    <?php else: ?>
                                        <select class="form-select<?= $invalid('category_id') ?>" id="category_id" name="category_id" required>
                                            <option value="">Choose…</option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= (int) $c['category_id'] ?>"<?= $sel($old['category_id'], $c['category_id']) ?>><?= e($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?= $feedback('category_id') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="location_id">Branch</label>
                                    <select class="form-select<?= $invalid('location_id') ?>" id="location_id" name="location_id" required>
                                        <option value="">Choose…</option>
                                        <?php foreach ($locations as $l): ?>
                                            <option value="<?= (int) $l['location_id'] ?>"<?= $sel($old['location_id'], $l['location_id']) ?>><?= e($l['city'] . ' · ' . $l['address']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?= $feedback('location_id') ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h3 class="form-section-title"><span class="n">2</span> Details</h3>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <label class="form-label" for="age">Age (years)</label>
                                    <input class="form-control<?= $invalid('age') ?>" id="age" name="age" type="number" min="0" max="40" value="<?= e($old['age']) ?>" placeholder="0 = under 1">
                                    <?= $feedback('age') ?>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" for="gender">Gender</label>
                                    <select class="form-select<?= $invalid('gender') ?>" id="gender" name="gender">
                                        <option value="">Unknown</option>
                                        <?php foreach (PET_GENDERS as $g): ?><option<?= $sel($old['gender'], $g) ?>><?= $g ?></option><?php endforeach; ?>
                                    </select>
                                    <?= $feedback('gender') ?>
                                </div>
                                <div class="col-sm-4">
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
                            <h3 class="form-section-title"><span class="n">3</span> Photo</h3>
                            <div class="file-drop file-drop-row">
                                <div class="ph" id="addPetPh" aria-hidden="true"><i class="bi bi-image"></i></div>
                                <img id="addPetPreview" class="image-preview d-none" alt="Preview of the selected photo">
                                <div class="min-w-0">
                                    <label class="form-label" for="picture">Choose a photo</label>
                                    <input class="form-control<?= $invalid('picture') ?>" type="file" id="picture" name="picture" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#addPetPreview" onchange="document.getElementById('addPetPh').classList.add('d-none')" required>
                                    <?= $feedback('picture') ?>
                                    <div class="form-text">JPG, PNG, WEBP or GIF up to 5 MB.<?= $errors && !isset($errors['picture']) ? ' Please choose the photo again.' : '' ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h3 class="form-section-title"><span class="n">4</span> Profile text</h3>
                            <div class="mb-3">
                                <label class="form-label" for="intro1">Introduction</label>
                                <textarea class="form-control<?= $invalid('intro1') ?>" id="intro1" name="intro1" rows="3" placeholder="Personality, history, what makes them special…" required><?= e($old['intro1']) ?></textarea>
                                <?= $feedback('intro1') ?>
                            </div>
                            <label class="form-label" for="intro2">Health &amp; home needs <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea class="form-control" id="intro2" name="intro2" rows="2" placeholder="Vaccinations, training, ideal home…"><?= e($old['intro2']) ?></textarea>
                        </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add pet</button>
            </div>
        </form>
    </div>
</div>
<?php if ($openModal): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addPetModal')).show();
    });
</script>
<?php endif; ?>
<?php page_end(); ?>
