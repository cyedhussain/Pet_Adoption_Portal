<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();
handle_favourite_toggle($adopterId);

$categoryId = query_int('cindex');
$category = db_one("SELECT * FROM `categories` WHERE `category_id` = ?", [$categoryId]);
if (!$category) {
    flash('That category could not be found.', 'warning');
    redirect('adopter/categories.php');
}

$filters = pet_filters_from_request();
$filters['category'] = $categoryId;
$pets = find_pets($filters);
$favouriteIds = favourite_ids($adopterId);

$pageTitle = $category['name'];
$activeNav = 'categories';
include __DIR__ . '/header.php';
render_page_hero($category['name'], $category['description'], 'img/category/' . $category['image'], 'Adopt ' . strtolower($category['name']),
    [['Dashboard', url('adopter/home.php')], ['Animals', url('adopter/categories.php')]]);
?>
<section class="pb-5">
    <div class="container">
        <?php render_category_stats(category_stats()[$categoryId] ?? null, $category['name']); ?>
        <?php render_pet_browser($pets, $filters, url('adopter/categoryPets.php'), [
            'hidden'       => ['cindex' => $categoryId],
            'showCategory' => false,
            'noun'         => strtolower($category['name']),
            'detailUrl'    => fn($pet) => url('adopter/petInfo.php?pindex=' . (int) $pet['pet_id']),
            'favouriteIds' => $favouriteIds,
            'emptyText'    => 'No ' . strtolower($category['name']) . ' match your search right now.',
            'metaLink'     => '<a class="small fw-bold" href="' . e(url('adopter/categories.php')) . '"><i class="bi bi-arrow-left"></i> All animals</a>',
        ]); ?>
    </div>
</section>
<?php page_end(); ?>
