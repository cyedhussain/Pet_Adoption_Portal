<?php
require_once __DIR__ . '/dbconnect.php';

$petId = query_int('pindex');
$pet = find_pet($petId);

// Logged-in adopters get the version of this page with favourites and the adoption form.
if ($pet && current_role() === 'adopter') {
    redirect('adopter/petInfo.php?pindex=' . $petId);
}

$related = $pet ? find_pets(['category' => $pet['category_id'], 'exclude' => $petId], 4) : [];
$adoptUrl = url('login.php?next=' . rawurlencode('adopter/petInfo.php?pindex=' . $petId));

$pageTitle = $pet ? 'Meet ' . $pet['name'] : 'Pet not found';
$activeNav = 'pets';
include __DIR__ . '/header.php';
?>
<?php if (!$pet): ?>
    <section class="section">
        <div class="container" style="max-width:720px">
            <?php render_empty_state('bi-search-heart', 'Pet not found', 'This pet may already have found a home. There are plenty more friends waiting to meet you.', 'Browse pets', url('pets.php')); ?>
        </div>
    </section>
<?php else:
    if ((int) $pet['requested'] !== 0) {
        $actions = '<p class="note note-warning"><i class="bi bi-hourglass-split"></i><span>Someone has already applied to adopt ' . e($pet['name'])
                 . '. If that application doesn’t go ahead, ' . e($pet['name']) . ' will be available again.</span></p>'
                 . '<a class="btn btn-outline-primary w-100 mt-3" href="' . e(url('pets.php')) . '">See other pets</a>';
    } else {
        $actions = '<div class="adopt-actions">'
                 . '<a class="btn btn-primary btn-lg" href="' . e($adoptUrl) . '"><i class="bi bi-heart-fill"></i> Adopt ' . e(excerpt($pet['name'], 18)) . '</a>'
                 . '<a class="btn btn-outline-primary" href="' . e(url('signup.php')) . '">New here? Create a free account</a>'
                 . '</div><p class="small text-muted text-center mt-2 mb-0">You’ll log in first, then fill in a short application.</p>';
    }
?>
    <section class="pet-detail">
        <div class="container">
            <?php render_pet_profile($pet, [
                'crumbs' => array_merge([['Home', url('index.php')], ['Pets', url('pets.php')]],
                    !empty($pet['category_name']) ? [[$pet['category_name'], url('sub_categories.php?cindex=' . (int) $pet['category_id'])]] : []),
                'actionsHtml' => $actions,
            ]); ?>

            <?php if ($related): ?>
                <div class="section-row mt-5 pt-4">
                    <div class="section-head">
                        <span class="eyebrow">You might also like</span>
                        <h2>More <?= e(strtolower($pet['category_name'] ?? 'pets')) ?> looking for a home</h2>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= e(url('sub_categories.php?cindex=' . (int) $pet['category_id'])) ?>">View all <i class="bi bi-arrow-right"></i></a>
                </div>
                <?php render_pet_grid($related, fn($p) => url('petInfo.php?pindex=' . (int) $p['pet_id'])); ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?php page_end(); ?>
