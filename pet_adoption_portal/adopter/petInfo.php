<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();
handle_favourite_toggle($adopterId);

$petId = query_int('pindex');
$adopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);
$pet = find_pet($petId);

// A pet leaves the `pets` table once adopted; the adopter can still see their own adopted pet.
$adoptedRecord = null;
if (!$pet) {
    $adoptedRecord = db_one(
        "SELECT * FROM `adopted` WHERE `pet_id` = ? AND (`adopter_id` = ? OR (`adopter_id` IS NULL AND `email` = ?))",
        [$petId, $adopterId, $adopter['email']]);
    if (!$adoptedRecord) {
        flash('That pet could not be found. It may already have found a home.', 'warning');
        redirect('adopter/pets.php');
    }
    $pet = [
        'pet_id' => $petId, 'name' => $adoptedRecord['pet_name'], 'image' => $adoptedRecord['image'],
        'intro1' => $adoptedRecord['pet_intro1'], 'intro2' => $adoptedRecord['pet_intro2'],
        'category_name' => $adoptedRecord['pet_category'], 'category_id' => 0, 'breed' => '', 'age' => null,
        'gender' => '', 'size' => '', 'vaccinated' => 0, 'requested' => 1,
        'city' => $adoptedRecord['location_city'], 'location_address' => $adoptedRecord['location_address'],
        'location_email' => $adoptedRecord['location_email'], 'location_contact' => $adoptedRecord['location_contact'],
    ];
}

// This adopter's own application for the pet, if any.
$myApplication = null;
foreach (adopter_applications($adopterId, $adopter['email']) as $app) {
    if ((int) $app['pet_id'] === $petId && in_array($app['stage'], ['pending', 'accepted', 'adopted'], true)) {
        $myApplication = $app;
        break;
    }
}

$isFavourite = !$adoptedRecord && in_array($petId, favourite_ids($adopterId), true);
$related = $adoptedRecord ? [] : find_pets(['category' => (int) $pet['category_id'], 'exclude' => $petId], 4);
if (!$adoptedRecord && count($related) < 4) {
    $seen = array_merge([$petId], array_map('intval', array_column($related, 'pet_id')));
    foreach (find_pets([], 8) as $extra) {
        if (count($related) >= 4) {
            break;
        }
        if (!in_array((int) $extra['pet_id'], $seen, true)) {
            $related[] = $extra;
        }
    }
}

$favHtml = $adoptedRecord ? '' : '<form method="post" class="fav-form">' . csrf_field()
    . '<input type="hidden" name="fav_toggle" value="' . (int) $petId . '">'
    . '<button class="fav-btn' . ($isFavourite ? ' is-active' : '') . '" aria-label="' . ($isFavourite ? 'Remove ' . e($pet['name']) . ' from favourites' : 'Save ' . e($pet['name']) . ' to favourites') . '" aria-pressed="' . ($isFavourite ? 'true' : 'false') . '">'
    . '<i class="bi bi-heart' . ($isFavourite ? '-fill' : '') . '"></i></button></form>';

if ($myApplication) {
    $actions = '<div class="info-panel bg-light border-0 p-3">'
             . '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><strong>Your application</strong>' . stage_badge($myApplication['stage']) . '</div>'
             . '<p class="text-muted small mb-3">Applied on ' . e(format_date($myApplication['request_time'])) . '.'
             . ($myApplication['stage'] === 'accepted' ? ' Please pick ' . e($pet['name']) . ' up from the ' . e($pet['city']) . ' branch within 3 days.' : '')
             . ($myApplication['stage'] === 'adopted' ? ' Adopted on ' . e(format_date($myApplication['adopt_time'])) . '. Thank you for giving ' . e($pet['name']) . ' a home!' : '')
             . '</p><a class="btn btn-primary w-100" href="' . e(url('adopter/requested.php')) . '"><i class="bi bi-clipboard2-heart"></i> Track my application</a></div>';
} elseif ((int) $pet['requested'] !== 0) {
    $actions = '<p class="note note-warning"><i class="bi bi-hourglass-split"></i><span>Someone has already applied to adopt ' . e($pet['name'])
             . '. Save them to your favourites — if the application doesn’t go ahead, they will be back on the list.</span></p>';
} else {
    $actions = '<div class="adopt-actions"><a class="btn btn-primary btn-lg" href="' . e(url('adopter/adoptForm.php?pindex=' . (int) $petId)) . '"><i class="bi bi-house-heart"></i> Apply to adopt ' . e(excerpt($pet['name'], 18)) . '</a>'
             . '<form method="post" class="d-grid">' . csrf_field() . '<input type="hidden" name="fav_toggle" value="' . (int) $petId . '">'
             . '<button class="btn btn-outline-primary"><i class="bi bi-heart' . ($isFavourite ? '-fill text-accent' : '') . '"></i> ' . ($isFavourite ? 'Saved to favourites' : 'Save for later') . '</button></form></div>'
             . '<p class="small text-muted text-center mt-2 mb-0">Once approved, you have 3 days to pick your new friend up.</p>';
}

$pageTitle = 'Meet ' . $pet['name'];
$activeNav = 'pets';
include __DIR__ . '/header.php';
?>
<section class="pet-detail">
    <div class="container">
        <?php render_pet_profile($pet, [
            'crumbs' => array_merge([['Dashboard', url('adopter/home.php')], ['Find a pet', url('adopter/pets.php')]],
                !empty($pet['category_id']) ? [[$pet['category_name'], url('adopter/categoryPets.php?cindex=' . (int) $pet['category_id'])]] : []),
            'favHtml'     => $favHtml,
            'actionsHtml' => $actions,
            'minimal'     => (bool) $adoptedRecord,
        ]); ?>

        <?php if ($related): ?>
            <div class="section-row mt-5 pt-4">
                <div class="section-head">
                    <span class="eyebrow">You might also love</span>
                    <h2>More friends looking for a home</h2>
                </div>
                <a class="btn btn-outline-primary" href="<?= e(url('adopter/pets.php')) ?>">See all pets <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php render_pet_grid($related, fn($p) => url('adopter/petInfo.php?pindex=' . (int) $p['pet_id']), favourite_ids($adopterId)); ?>
        <?php endif; ?>
    </div>
</section>
<?php page_end(); ?>
