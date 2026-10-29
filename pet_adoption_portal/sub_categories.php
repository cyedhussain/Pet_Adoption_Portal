<?php
require_once __DIR__ . '/dbconnect.php';

$categoryId = query_int('cindex');
$category = db_one("SELECT * FROM `categories` WHERE `category_id` = ?", [$categoryId]);

$filters = pet_filters_from_request();
$filters['category'] = $categoryId;
$pets = $category ? find_pets($filters) : [];

$pageTitle = $category ? $category['name'] : 'Category not found';
$activeNav = 'categories';
include __DIR__ . '/header.php';
?>
<?php if (!$category): ?>
    <section class="section">
        <div class="container" style="max-width:720px">
            <?php render_empty_state('bi-question-circle', 'Category not found', 'This category does not exist or has been removed.', 'Browse all animals', url('categories.php')); ?>
        </div>
    </section>
<?php else: ?>
    <?php render_page_hero($category['name'], $category['description'], 'img/category/' . $category['image'], 'Adopt ' . strtolower($category['name']),
        [['Home', url('index.php')], ['Animals', url('categories.php')]]); ?>
    <section class="pb-5">
        <div class="container">
            <?php render_category_stats(category_stats()[$categoryId] ?? null, $category['name']); ?>
            <?php render_pet_browser($pets, $filters, url('sub_categories.php'), [
                'hidden'       => ['cindex' => $categoryId],
                'showCategory' => false,
                'noun'         => strtolower($category['name']),
                'detailUrl'    => fn($pet) => url('petInfo.php?pindex=' . (int) $pet['pet_id']),
                'emptyText'    => 'No ' . strtolower($category['name']) . ' match right now. Try another filter, or check back soon.',
                'metaLink'     => '<a class="small fw-bold" href="' . e(url('categories.php')) . '"><i class="bi bi-arrow-left"></i> All animals</a>',
            ]); ?>
        </div>
    </section>
<?php endif; ?>
<?php page_end(); ?>
