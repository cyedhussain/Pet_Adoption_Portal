<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();
handle_favourite_toggle($adopterId);

$filters = pet_filters_from_request();
$pets = find_pets($filters);
$favouriteIds = favourite_ids($adopterId);

$pageTitle = 'Find a pet';
$activeNav = 'pets';
include __DIR__ . '/header.php';
render_page_hero('Find your new best friend', 'Every pet here is health-checked and waiting for a loving home. Tap the heart to save the ones you love.', '', 'Adopt, don’t shop', [['Dashboard', url('adopter/home.php')]]);
?>
<section class="pb-5">
    <div class="container">
        <?php render_pet_browser($pets, $filters, url('adopter/pets.php'), [
            'detailUrl'    => fn($pet) => url('adopter/petInfo.php?pindex=' . (int) $pet['pet_id']),
            'favouriteIds' => $favouriteIds,
            'metaLink'     => '<a class="small fw-bold" href="' . e(url('adopter/home.php#favourites')) . '"><i class="bi bi-heart-fill text-accent"></i> ' . count($favouriteIds) . ' saved</a>',
        ]); ?>
    </div>
</section>
<?php page_end(); ?>
