<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();
handle_favourite_toggle($adopterId);

$adopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);
$applications = adopter_applications($adopterId, $adopter['email']);
$stageCount = fn($stage) => count(array_filter($applications, fn($a) => $a['stage'] === $stage));
$favourites = favourite_pets($adopterId);
$favouriteIds = array_map('intval', array_column($favourites, 'pet_id'));

// Recommendations: available pets from the categories this adopter has shown interest in, then the newest.
// $interestReason is category_id => why it is recommended ("Because you saved a dog").
$interestReason = [];
foreach ($favourites as $fav) {
    $interestReason[(int) $fav['category_id']] ??= 'Because you saved a ' . animal_word($fav['category_name']);
}
foreach (db_all(
    "SELECT p.`category_id`, c.`name` FROM `requests` r JOIN `pets` p ON p.`pet_id` = r.`pet_id`
     LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
     WHERE r.`adopter_id` = ? OR (r.`adopter_id` IS NULL AND r.`email` = ?)",
    [$adopterId, $adopter['email']]) as $row) {
    $interestReason[(int) $row['category_id']] ??= 'Because you applied for a ' . animal_word($row['name']);
}
unset($interestReason[0]); // pets without a category
$interestCategories = array_keys(array_filter($interestReason));
$candidates = [];
foreach ($interestCategories as $categoryId) {
    foreach (find_pets(['category' => $categoryId]) as $pet) {
        $candidates[] = [$pet, $interestReason[$categoryId]];
    }
}
foreach (find_pets([], 12) as $pet) {
    $candidates[] = [$pet, pet_is_new($pet) ? 'New this week' : 'Recently listed'];
}
$recommended = [];
$recommendReason = [];
foreach ($candidates as [$pet, $reason]) {
    if (count($recommended) >= 4) {
        break;
    }
    if (!in_array((int) $pet['pet_id'], $favouriteIds, true) && !isset($recommended[$pet['pet_id']])) {
        $recommended[$pet['pet_id']] = $pet;
        $recommendReason[$pet['pet_id']] = $reason;
    }
}
$newThisWeek = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0 AND `created_at` >= CURDATE() - INTERVAL 6 DAY");
$hasInterests = (bool) $interestCategories;
$firstName = explode(' ', trim($adopter['name']))[0];

$pendingCount = $stageCount('pending');
$pickupCount = $stageCount('accepted');
$messages = db_all("SELECT * FROM `contact` WHERE `email` = ? ORDER BY `timestamp` DESC LIMIT 3", [$adopter['email']]);
$heroPets = array_slice(array_values($recommended), 0, 3);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Dashboard';
$activeNav = 'home';
include __DIR__ . '/header.php';
?>
<section class="pt-4 pt-lg-5">
    <div class="container">
        <div class="dash-hero reveal">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <span class="eyebrow eyebrow-light">Your adopter dashboard</span>
                    <h1><?= e($greeting) ?>, <?= e($firstName) ?>!</h1>
                    <p class="mb-4">
                        <?php if ($pickupCount): ?>
                            Great news — <?= $pickupCount === 1 ? 'an application is' : $pickupCount . ' applications are' ?> approved and ready for pickup.
                        <?php elseif ($pendingCount): ?>
                            Our team is reviewing <?= $pendingCount === 1 ? 'your application' : 'your ' . $pendingCount . ' applications' ?>. We’ll email you as soon as there is news.
                        <?php else: ?>
                            Browse pets waiting for a home, save your favourites and follow each application right here.
                        <?php endif; ?>
                    </p>
                    <a class="new-week" href="<?= e(url('adopter/pets.php?sort=newest')) ?>">
                        <span class="dot"><i class="bi bi-stars"></i></span>
                        <?= $newThisWeek ? '<strong>' . plural($newThisWeek, 'new pet') . '</strong> listed this week' : 'No new pets this week yet — see who’s waiting' ?>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-white btn-lg" href="<?= e(url('adopter/pets.php')) ?>"><i class="bi bi-search-heart"></i> Find a pet</a>
                        <a class="btn btn-glass btn-lg" href="<?= e(url('adopter/requested.php')) ?>"><i class="bi bi-clipboard2-heart"></i> My adoptions</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="hero-pets">
                        <?php foreach ($heroPets as $pet): ?>
                            <img src="<?= e(pet_image_url($pet['image'])) ?>" alt="<?= e($pet['name']) ?>"<?= img_fallback() ?>>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <?php foreach ([
                ['adopter/requested.php?tab=pending', $pendingCount, 'Under review', 'hourglass-split', 'tone-warning'],
                ['adopter/requested.php?tab=accepted', $pickupCount, 'Ready for pickup', 'calendar-check', 'tone-info'],
                ['adopter/requested.php?tab=adopted', $stageCount('adopted'), 'Adopted', 'house-heart', 'tone-success'],
                ['adopter/home.php#favourites', count($favourites), 'Saved pets', 'heart-fill', 'tone-accent'],
            ] as [$href, $value, $label, $icon, $tone]): ?>
                <div class="col-6 col-lg-3">
                    <a class="mini-stat" href="<?= e(url($href)) ?>">
                        <span class="icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span>
                        <div class="min-w-0"><strong><?= (int) $value ?></strong><span><?= e($label) ?></span></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                    <div><span class="eyebrow">Application tracker</span><h2 class="mb-0">Your applications</h2></div>
                    <?php if ($applications): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(url('adopter/requested.php')) ?>">View all <?= count($applications) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                </div>
                <?php if (!$applications): ?>
                    <?php render_empty_state('bi-clipboard2-heart', 'No applications yet', 'When you apply to adopt a pet, you can follow every step of it here.', 'Browse pets', url('adopter/pets.php')); ?>
                <?php else: ?>
                    <div class="app-list">
                        <?php foreach (array_slice($applications, 0, 3) as $app) {
                            render_application_card($app);
                        } ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4 d-flex flex-column gap-3">
                <div class="info-panel account-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-lg"><?= e(strtoupper(mb_substr($adopter['name'], 0, 1))) ?></span>
                        <div class="min-w-0"><strong class="d-block text-truncate"><?= e($adopter['name']) ?></strong><small class="text-muted">Adopter account</small></div>
                    </div>
                    <dl>
                        <dt>Email</dt><dd><?= e($adopter['email']) ?></dd>
                        <dt>CNIC</dt><dd><?= e(substr((string) $adopter['cnic'], 0, 5) . '-•••••••-' . substr((string) $adopter['cnic'], -1)) ?></dd>
                        <dt>Member since</dt><dd><?= e(format_date($adopter['created_at'])) ?></dd>
                    </dl>
                </div>
                <div class="info-panel">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="mb-0"><i class="bi bi-chat-heart text-brand"></i> Messages</h3>
                        <a class="small fw-bold" href="<?= e(url('adopter/contactUs.php#messages')) ?>">Open</a>
                    </div>
                    <?php if (!$messages): ?>
                        <p class="text-muted small mb-3">Questions about a pet or your application? Our team replies here and by email.</p>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('adopter/contactUs.php')) ?>"><i class="bi bi-send"></i> Send a message</a>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-grid gap-2">
                            <?php foreach ($messages as $m): ?>
                                <li class="small">
                                    <div class="d-flex justify-content-between gap-2"><span class="text-muted fw-semibold"><?= e(format_date($m['timestamp'])) ?></span>
                                        <?= $m['answer'] === '' ? '<span class="status-pill status-warning">Awaiting reply</span>' : '<span class="status-pill status-success">Answered</span>' ?></div>
                                    <div class="text-truncate fw-semibold mt-1"><?= e($m['message']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="info-panel">
                    <h3><i class="bi bi-signpost-2 text-brand"></i> How adoption works</h3>
                    <ol class="mini-steps mt-0 pt-0 border-0">
                        <li><span class="n">1</span><div><strong>Apply</strong>Pick a pet and send a short form.</div></li>
                        <li><span class="n">2</span><div><strong>Review</strong>The shelter decides and emails you.</div></li>
                        <li><span class="n">3</span><div><strong>Pick up</strong>Collect your pet within 3 days.</div></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-sm pt-0" id="favourites">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div><span class="eyebrow">Saved for later</span><h2 class="mb-0">Your favourites</h2></div>
            <?php if ($favourites): ?><span class="text-muted fw-semibold"><?= count($favourites) ?> saved</span><?php endif; ?>
        </div>
        <?php if (!$favourites): ?>
            <?php render_empty_state('bi-heart', 'No saved pets yet', 'Tap the heart on any pet to keep them here while you decide.', 'Find a pet', url('adopter/pets.php')); ?>
        <?php else: ?>
            <?php render_pet_grid($favourites, fn($pet) => url('adopter/petInfo.php?pindex=' . (int) $pet['pet_id']), $favouriteIds); ?>
            <?php if (array_filter($favourites, fn($p) => (int) $p['requested'] !== 0)): ?>
                <p class="note mt-3"><i class="bi bi-info-circle"></i><span>Some saved pets already have an application in progress. If it doesn’t go ahead, they’ll be available again.</span></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($recommended): ?>
<section class="section-sm bg-cream">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <div><span class="eyebrow"><?= $hasInterests ? 'Based on your interests' : 'Newly arrived' ?></span><h2 class="mb-0">Recommended for you</h2></div>
            <a class="btn btn-outline-primary" href="<?= e(url('adopter/pets.php')) ?>">See all pets <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="pet-grid">
            <?php foreach ($recommended as $pet): ?>
                <div class="rec-item">
                    <span class="rec-reason"><i class="bi bi-lightbulb"></i> <?= e($recommendReason[$pet['pet_id']]) ?></span>
                    <?php render_pet_card($pet, url('adopter/petInfo.php?pindex=' . (int) $pet['pet_id']), false); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php page_end(); ?>
