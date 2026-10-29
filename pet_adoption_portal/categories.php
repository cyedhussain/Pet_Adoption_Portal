<?php
require_once __DIR__ . '/dbconnect.php';

$categories = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS pet_count
     FROM `categories` c ORDER BY c.`name`"
);

$pageTitle = 'Animals';
$activeNav = 'categories';
$stats = category_stats();
include __DIR__ . '/header.php';
render_page_hero('Browse by animal', 'Cats, dogs, rabbits and more. Pick an animal to see who is waiting for a home.', 'img/20.jpg', 'Categories', [['Home', url('index.php')]]);
?>
<section class="pb-5">
    <div class="container">
        <?php if (!$categories): ?>
            <?php render_empty_state('bi-tags', 'No categories yet', 'Our team is still setting things up. Please check back soon.'); ?>
        <?php else: ?>
            <div class="cat-grid">
                <?php foreach ($categories as $c): ?>
                    <a class="cat-tile" href="<?= e(url('sub_categories.php?cindex=' . (int) $c['category_id'])) ?>" data-animate>
                        <div>
                            <h3><?= e($c['name']) ?></h3>
                            <p><?= e($c['description']) ?></p>
                            <?= category_tile_facts($stats[(int) $c['category_id']] ?? null) ?>
                        </div>
                        <div class="bottom">
                            <span class="count"><i class="bi bi-heart-fill"></i> <?= (int) $c['pet_count'] ?> available</span>
                            <span class="go" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
                        </div>
                        <img src="<?= e(category_image_url($c['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="text-center mt-5">
            <a class="btn btn-primary btn-lg" href="<?= e(url('pets.php')) ?>"><i class="bi bi-search-heart"></i> Browse every pet</a>
        </div>
    </div>
</section>
<?php page_end(); ?>
