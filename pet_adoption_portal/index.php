<?php
require_once __DIR__ . '/dbconnect.php';

$isAdopter = current_role() === 'adopter';
$base = $isAdopter ? 'adopter/' : '';

$availableCount = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0");
$adoptedCount = (int) db_value("SELECT COUNT(*) FROM `adopted`");
$branchCount = (int) db_value("SELECT COUNT(*) FROM `locations`");
$vaccinatedCount = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0 AND `vaccinated` = 1");
$categories = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM `pets` p WHERE p.`category_id` = c.`category_id` AND p.`requested` = 0) AS pet_count
     FROM `categories` c ORDER BY c.`name`"
);
$newest = find_pets([], 8);
$adoptedThisYear = (int) db_value("SELECT COUNT(*) FROM `adopted` WHERE YEAR(`adopt_time`) = YEAR(CURDATE())");
$avgDaysToHome = db_value("SELECT ROUND(AVG(GREATEST(DATEDIFF(`adopt_time`, `request_time`), 0))) FROM `adopted`");
$newThisWeek = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0 AND `created_at` >= CURDATE() - INTERVAL 6 DAY");
// decisions = approvals (accept_time) and refusals (updated_at), measured from the request
$avgDecisionDays = db_value(
    "SELECT ROUND(AVG(d)) FROM (
        SELECT GREATEST(DATEDIFF(`accept_time`, `request_time`), 0) AS d FROM `accepted`
        UNION ALL SELECT GREATEST(DATEDIFF(`accept_time`, `request_time`), 0) FROM `adopted`
        UNION ALL SELECT GREATEST(DATEDIFF(`updated_at`, `request_time`), 0) FROM `requests` WHERE `status` = 'rejected' AND `updated_at` IS NOT NULL
     ) t");
$happyTails = db_all(
    "SELECT `pet_name`, `pet_category`, `image`, `location_city`, `adopt_time`,
            GREATEST(DATEDIFF(`adopt_time`, `request_time`), 0) AS days_taken
     FROM `adopted` ORDER BY `adopt_time` DESC LIMIT 4");
$branches = all_locations();
$branchCounts = [];
foreach (db_all(
    "SELECT p.`location_id`, p.`category_id`, c.`name`, COUNT(*) AS n
     FROM `pets` p JOIN `categories` c ON c.`category_id` = p.`category_id`
     WHERE p.`requested` = 0 GROUP BY p.`location_id`, p.`category_id`, c.`name` ORDER BY c.`name`") as $row) {
    $branchCounts[(int) $row['location_id']][] = $row;
}
$branchAdopted = [];
foreach (db_all("SELECT `location_city`, COUNT(*) AS n FROM `adopted` GROUP BY `location_city`") as $row) {
    $branchAdopted[$row['location_city']] = (int) $row['n'];
}
$spotlight = $newest[0] ?? null;
$petUrl = fn($pet) => url($base . 'petInfo.php?pindex=' . (int) $pet['pet_id']);
$categoryUrl = fn($c) => url($isAdopter ? 'adopter/categoryPets.php?cindex=' . (int) $c['category_id'] : 'sub_categories.php?cindex=' . (int) $c['category_id']);

$pageTitle = 'Adopt a rescued pet';
$activeNav = 'home';
include __DIR__ . '/header.php';
?>
<section class="hero-home">
    <div class="container">
        <div class="hero-grid">
            <div class="reveal">
                <span class="hero-pill"><span class="dot"><i class="bi bi-heart-fill"></i></span>
                    <span><strong><?= $availableCount ?></strong> <?= $availableCount === 1 ? 'pet is' : 'pets are' ?> waiting for a home right now</span></span>
                <h1>Find the friend who’s been <span class="highlight">waiting for you.</span></h1>
                <p class="hero-lead">Animal Matter to Me connects rescued cats, dogs and rabbits with caring families. Every animal is health-checked by our team, and you can follow your adoption online from application to pickup.</p>

                <form class="search-card" method="get" action="<?= e(url($base . 'pets.php')) ?>" role="search">
                    <div class="species-tabs" role="radiogroup" aria-label="Animal">
                        <input type="radio" name="category" id="sp-all" value="" checked><label for="sp-all"><i class="bi bi-stars"></i> All pets</label>
                        <?php foreach ($categories as $c): ?>
                            <input type="radio" name="category" id="sp-<?= (int) $c['category_id'] ?>" value="<?= (int) $c['category_id'] ?>"><label for="sp-<?= (int) $c['category_id'] ?>"><?= e($c['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="search-row">
                        <label class="visually-hidden" for="heroSearch">Search by name or breed</label>
                        <input class="form-control" type="search" id="heroSearch" name="q" placeholder="Name or breed, e.g. Shepherd">
                        <label class="visually-hidden" for="heroBranch">Branch</label>
                        <select class="form-select" id="heroBranch" name="location">
                            <option value="">Any branch</option>
                            <?php foreach (all_locations() as $l): ?>
                                <option value="<?= (int) $l['location_id'] ?>"><?= e($l['city']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                    </div>
                </form>

                <div class="trust-row">
                    <span><i class="bi bi-shield-check"></i> Health-checked pets</span>
                    <span><i class="bi bi-geo-alt"></i> <?= $branchCount ?> <?= $branchCount === 1 ? 'branch' : 'branches' ?> to visit</span>
                    <span><i class="bi bi-clipboard2-check"></i> Track your application online</span>
                </div>
            </div>

            <div class="hero-visual reveal reveal-2">
                <span class="blob" aria-hidden="true"></span>
                <span class="blob-sun" aria-hidden="true"></span>
                <img class="main-img" src="<?= e(url('img/20.jpg')) ?>" alt="A cat and two dogs sitting together" fetchpriority="high">
                <?php if ($spotlight): ?>
                    <a class="float-card fc-1" href="<?= e($petUrl($spotlight)) ?>">
                        <img src="<?= e(pet_image_url($spotlight['image'])) ?>" alt=""<?= img_fallback() ?>>
                        <span class="min-w-0"><small>Newest arrival</small><span class="d-block text-truncate">Meet <?= e($spotlight['name']) ?></span></span>
                    </a>
                <?php endif; ?>
                <div class="float-card fc-2">
                    <span class="icon tone-success"><i class="bi bi-house-heart-fill"></i></span>
                    <span><?= $adoptedCount ?> happy <?= $adoptedCount === 1 ? 'adoption' : 'adoptions' ?><small>completed through this portal</small></span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="glance" data-animate>
            <div class="glance-head">
                <span class="eyebrow">Live from our shelters</span>
                <h2>Shelter at a glance</h2>
                <p>Updated every time a pet is listed or adopted.</p>
            </div>
            <dl class="glance-grid">
                <div><dt>Available now</dt><dd><?= $availableCount ?></dd><small><?= $newThisWeek ? plural($newThisWeek, 'new arrival') . ' this week' : 'Ready to meet you' ?></small></div>
                <div><dt>Adopted all time</dt><dd><?= $adoptedCount ?></dd><small>Through this portal</small></div>
                <div><dt>Adopted in <?= date('Y') ?></dt><dd><?= $adoptedThisYear ?></dd><small>Since January 1</small></div>
                <div><dt>Application to home</dt><dd><?= $avgDaysToHome !== null ? (int) $avgDaysToHome . '<span>' . ((int) $avgDaysToHome === 1 ? 'day' : 'days') . '</span>' : '—' ?></dd><small><?= $avgDaysToHome !== null ? 'Average, across past adoptions' : 'Shown after the first adoption' ?></small></div>
                <div><dt>Branches</dt><dd><?= $branchCount ?></dd><small><?= e(implode(' · ', array_column($branches, 'city'))) ?></small></div>
                <div><dt>Newest arrival</dt><dd class="glance-pet"><?php if ($spotlight): ?><a href="<?= e($petUrl($spotlight)) ?>"><?= e($spotlight['name']) ?></a><?php else: ?>—<?php endif; ?></dd><small><?= $spotlight ? e(($spotlight['category_name'] ?? 'Pet') . ' · ' . strtolower(pet_care_label($spotlight))) : 'No pets listed right now' ?></small></div>
            </dl>
        </div>
    </div>
</section>

<?php if ($categories): ?>
<section class="section pt-0">
    <div class="container">
        <div class="section-row">
            <div class="section-head">
                <span class="eyebrow">Browse by animal</span>
                <h2>Who are you looking for?</h2>
                <p>Every animal has its own personality. Start with the kind of companion that suits your home.</p>
            </div>
            <a class="btn btn-outline-primary" href="<?= e(url($base . 'categories.php')) ?>">All animals <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="cat-grid">
            <?php foreach (array_slice($categories, 0, 4) as $c): ?>
                <a class="cat-tile" href="<?= e($categoryUrl($c)) ?>" data-animate>
                    <div>
                        <h3><?= e($c['name']) ?></h3>
                        <p><?= e($c['description']) ?></p>
                    </div>
                    <div class="bottom">
                        <span class="count"><i class="bi bi-heart-fill"></i> <?= (int) $c['pet_count'] ?> available</span>
                    </div>
                    <img src="<?= e(category_image_url($c['image'])) ?>" alt="" loading="lazy"<?= img_fallback() ?>>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section bg-cream">
    <div class="container">
        <div class="section-row">
            <div class="section-head">
                <span class="eyebrow">Newest arrivals</span>
                <h2>Ready to meet you</h2>
                <p>Recently arrived at our branches, health-checked and looking for a family of their own.</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url($base . 'pets.php')) ?>">See all <?= $availableCount ?> pets <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if ($newest): ?>
            <?php render_pet_grid(array_slice($newest, 0, 4), $petUrl); ?>
        <?php else: ?>
            <?php render_empty_state('bi-house-heart', 'Everyone has found a home', 'All our pets have been adopted for now. New arrivals are listed here as soon as they are ready.'); ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($happyTails): ?>
<section class="section-sm">
    <div class="container">
        <div class="section-row">
            <div class="section-head">
                <span class="eyebrow">Happy tails</span>
                <h2>Recently found their family</h2>
                <p>Pets adopted through this portal — from the first application to going home.</p>
            </div>
            <span class="tail-total"><i class="bi bi-house-heart-fill"></i> <?= plural($adoptedCount, 'adoption') ?> so far</span>
        </div>
        <div class="tails">
            <?php foreach ($happyTails as $t): $took = (int) $t['days_taken']; ?>
                <article class="tail" data-animate>
                    <img src="<?= e(pet_image_url($t['image'])) ?>" alt="<?= e($t['pet_name']) ?>" loading="lazy"<?= img_fallback() ?>>
                    <div class="min-w-0">
                        <span class="tail-tag"><i class="bi bi-check2-circle"></i> Adopted <?= e(format_date($t['adopt_time'])) ?></span>
                        <h3><?= e($t['pet_name']) ?></h3>
                        <p><?= e(trim($t['pet_category'] . ($t['location_city'] ? ' · ' . $t['location_city'] . ' branch' : ''), ' ·')) ?></p>
                        <small><i class="bi bi-stopwatch"></i> <?= $took === 0 ? 'Went home the same day' : 'Home ' . plural($took, 'day') . ' after applying' ?></small>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" id="process">
    <div class="container">
        <div class="process-band">
            <div class="section-head center position-relative" style="z-index:1">
                <span class="eyebrow">How adoption works</span>
                <h2>Four simple steps to a new best friend</h2>
                <p>A clear, kind process — and you can see exactly where your application is at every moment.</p>
            </div>
            <?php render_adoption_steps(); ?>
            <div class="text-center mt-5 position-relative" style="z-index:1">
                <a class="btn btn-accent btn-lg" href="<?= e(url($isAdopter ? 'adopter/pets.php' : (current_role() ? 'pets.php' : 'signup.php'))) ?>"><?= current_role() ? 'Find your pet' : 'Start your adoption' ?> <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">What we do</span>
            <h2>Protecting our voiceless friends</h2>
            <p>From the street to a safe, loving home — rescue, treatment and care at every step.</p>
        </div>
        <div class="bento">
            <div class="bento-photo" data-animate>
                <img src="<?= e(url('img/18.jpg')) ?>" alt="Our volunteers and staff together at the shelter" loading="lazy">
                <div class="caption">
                    <h3>Animal welfare is our top priority</h3>
                    <p>Since 2010 our team has run neutering and vaccination drives and emergency rescues for animals with no voice of their own.</p>
                </div>
            </div>
            <div class="service-card" data-animate>
                <img src="<?= e(url('img/12.jpg')) ?>" alt="" loading="lazy">
                <div><span class="icon"><i class="bi bi-shield-plus"></i></span><h3>Sterilization &amp; vaccination</h3><p>Humane drives that keep stray populations healthy and stop disease spreading.</p></div>
            </div>
            <div class="service-card" data-animate>
                <img src="<?= e(url('img/9.jpg')) ?>" alt="" loading="lazy">
                <div><span class="icon"><i class="bi bi-truck"></i></span><h3>Emergency rescue</h3><p>Critical help for animals injured in accidents or abandoned by their owners.</p></div>
            </div>
            <div class="service-card" data-animate>
                <img src="<?= e(url('img/14.jpg')) ?>" alt="" loading="lazy">
                <div><span class="icon"><i class="bi bi-heart-pulse"></i></span><h3>Medical care &amp; rehab</h3><p>Treatment, surgery and recovery so every animal is ready for a new home.</p></div>
            </div>
            <div class="bento-stat" data-animate>
                <div><div class="big-num"><?= $vaccinatedCount ?></div><span>Of <?= $availableCount ?> waiting are vaccinated</span></div>
                <div><div class="big-num"><?= count($categories) ?></div><span>Kinds of animals in our care</span></div>
                <div><div class="big-num"><?= $branchCount ?></div><span><?= $branchCount === 1 ? 'Branch' : 'Branches' ?> caring for them</span></div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Life at the shelter</span>
            <h2>Cared for, every single day</h2>
            <p>A look behind the scenes at our branches — feeding, grooming, check-ups and plenty of cuddles.</p>
        </div>
        <div class="photo-strip" data-animate>
            <img class="h2" src="<?= e(url('img/7.jpg')) ?>" alt="Volunteer preparing food for the shelter animals" loading="lazy">
            <img class="w2" src="<?= e(url('img/8.jpg')) ?>" alt="A rescued dog being cared for" loading="lazy">
            <img src="<?= e(url('img/10.jpg')) ?>" alt="A volunteer holding a puppy" loading="lazy">
            <img class="h2" src="<?= e(url('img/17.jpg')) ?>" alt="A spaniel during a check-up" loading="lazy">
            <img src="<?= e(url('img/11.jpg')) ?>" alt="Rescued dog with our team" loading="lazy">
            <img class="w2" src="<?= e(url('img/15.jpg')) ?>" alt="A dog resting after treatment" loading="lazy">
            <img src="<?= e(url('img/13.jpg')) ?>" alt="A dog on the examination table" loading="lazy">
        </div>
    </div>
</section>

<?php if ($branches): ?>
<section class="section pt-0" id="branches">
    <div class="container">
        <div class="section-row">
            <div class="section-head">
                <span class="eyebrow">Visit us</span>
                <h2>Our branches</h2>
                <p>Every pet lives at one of our branches — that’s where you meet them and pick them up after approval.</p>
            </div>
        </div>
        <div class="branch-grid">
            <?php foreach ($branches as $b):
                $counts = $branchCounts[(int) $b['location_id']] ?? [];
                $total = array_sum(array_map('intval', array_column($counts, 'n')));
                $adoptedHere = $branchAdopted[$b['city']] ?? 0; ?>
                <article class="branch-card" data-animate>
                    <div class="branch-card-head">
                        <span class="icon tone-brand"><i class="bi bi-geo-alt-fill"></i></span>
                        <div class="min-w-0"><h3><?= e($b['city']) ?></h3><p><?= e($b['address']) ?></p></div>
                    </div>
                    <div class="branch-numbers">
                        <div><strong><?= $total ?></strong><span>available</span></div>
                        <div><strong><?= $adoptedHere ?></strong><span>adopted</span></div>
                    </div>
                    <?php if ($counts): ?>
                        <div class="chips">
                            <?php foreach ($counts as $c): ?>
                                <a class="chip chip-link" href="<?= e(url($base . 'pets.php?location=' . (int) $b['location_id'] . '&category=' . (int) $c['category_id'])) ?>"><?= (int) $c['n'] ?> <?= e((int) $c['n'] === 1 ? animal_word($c['name']) : strtolower($c['name'])) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-0">No pets waiting here right now.</p>
                    <?php endif; ?>
                    <div class="branch-contact">
                        <a href="tel:<?= e($b['contact']) ?>"><i class="bi bi-telephone"></i> <?= e($b['contact']) ?></a>
                        <a href="mailto:<?= e($b['email']) ?>"><i class="bi bi-envelope"></i> <?= e($b['email']) ?></a>
                    </div>
                    <?php if ($total): ?><a class="btn btn-outline-primary w-100" href="<?= e(url($base . 'pets.php?location=' . (int) $b['location_id'])) ?>">See <?= plural($total, 'pet') ?> in <?= e($b['city']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section pt-0" id="faq">
    <div class="container">
        <div class="faq-layout">
            <div class="section-head">
                <span class="eyebrow">Good to know</span>
                <h2>Questions adopters ask</h2>
                <p>How adopting through Animal Matter to Me works, start to finish.</p>
                <a class="btn btn-outline-primary mt-3" href="<?= e(url($base . 'contactUs.php')) ?>"><i class="bi bi-chat-heart"></i> Ask us something else</a>
            </div>
            <div class="accordion faq" id="faqList">
                <?php foreach ([
                    ['Who can adopt?', 'Anyone with a free adopter account. You sign up with your name, email, a password and your 13-digit CNIC — one account per CNIC and email address.'],
                    ['How do I apply?', 'Open a pet’s page and choose “Apply to adopt”. The short form asks for your phone number and home address; your name, email and CNIC come from your account. While your application is being reviewed, the pet is reserved and no one else can apply.'],
                    ['How long does the review take, and how will I hear back?', 'Our team reviews every application by hand and emails you with the decision. You can also follow each step in “My adoptions”.' . ($avgDecisionDays !== null ? ' So far, decisions have taken about ' . plural((int) $avgDecisionDays, 'day') . ' on average.' : '')],
                    ['What happens when I’m approved?', 'You have 3 days to collect your pet from their branch — bring your CNIC with you. If you can’t make it in time, contact the branch; an approval that isn’t collected can be cancelled so the pet can meet someone else.'],
                    ['What if my application isn’t approved?', 'You’ll see the shelter’s reason in “My adoptions” and in your email. The pet becomes available again, and you’re welcome to apply for another pet at any time.'],
                    ['Can I cancel an application?', 'Yes — any time while it is still under review, from “My adoptions”. The pet goes straight back on the list for other adopters.'],
                    ['Can I save pets for later?', 'Yes. Log in and tap the heart on any pet. Your saved pets appear on your dashboard, along with recommendations based on the animals you like.'],
                ] as $i => [$q, $a]): ?>
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button<?= $i ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($q) ?></button></h3>
                        <div id="faq<?= $i ?>" class="accordion-collapse collapse<?= $i ? '' : ' show' ?>" data-bs-parent="#faqList"><div class="accordion-body"><?= e($a) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="cta-band" data-animate>
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <h2>Be a voice for the voiceless</h2>
                    <p class="mb-4">Open your home to a rescued animal and make a lasting difference to a life that matters.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-white btn-lg" href="<?= e(url($base . 'pets.php')) ?>"><i class="bi bi-search-heart"></i> Meet the pets</a>
                        <?php if (!current_role()): ?><a class="btn btn-glass btn-lg" href="<?= e(url('signup.php')) ?>">Create a free account</a><?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="cta-pets">
                        <?php foreach (array_slice($newest, 0, 4) as $pet): ?>
                            <img src="<?= e(pet_image_url($pet['image'])) ?>" alt="<?= e($pet['name']) ?>" loading="lazy"<?= img_fallback() ?>>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php page_end(); ?>
