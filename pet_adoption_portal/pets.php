<?php
require_once __DIR__ . '/dbconnect.php';

$filters = pet_filters_from_request();
$pets = find_pets($filters);

$pageTitle = 'Find a pet';
$activeNav = 'pets';
include __DIR__ . '/header.php';
render_page_hero('Find your new best friend', 'Every pet here is health-checked and waiting for a loving home. Filter by animal, branch, age or size.', '', 'Adopt, don’t shop', [['Home', url('index.php')]]);
?>
<section class="pb-5">
    <div class="container">
        <?php render_pet_browser($pets, $filters, url('pets.php'), [
            'detailUrl' => fn($pet) => url('petInfo.php?pindex=' . (int) $pet['pet_id']),
            'metaLink'  => '<a class="small fw-bold" href="' . e(url('login.php?next=' . rawurlencode('adopter/pets.php'))) . '"><i class="bi bi-heart"></i> Log in to save favourites</a>',
        ]); ?>
    </div>
</section>
<?php page_end(); ?>
