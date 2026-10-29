<?php
// Also served to logged-in adopters through adopter/aboutUs.php, with the adopter header.
require_once __DIR__ . '/dbconnect.php';

$isAdopter = current_role() === 'adopter';
$base = $isAdopter ? 'adopter/' : '';
$liveStats = [
    'available' => (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0"),
    'adopted'   => (int) db_value("SELECT COUNT(*) FROM `adopted`"),
    'branches'  => (int) db_value("SELECT COUNT(*) FROM `locations`"),
];
$team = [
    ['img/25.jpg', 'Tucker Andrews', 'Shelter manager'],
    ['img/26.jpg', 'Julien McCall', 'Veterinary lead'],
    ['img/27.jpg', 'Cameron Austin', 'Rescue coordinator'],
    ['img/28.jpg', 'Hannah Salgado', 'Adoption counsellor'],
    ['img/29.jpg', 'Lionel Williams', 'Animal care'],
    ['img/30.jpg', 'James Anderson', 'Community outreach'],
];

$pageTitle = 'About us';
$activeNav = 'about';
include __DIR__ . ($isAdopter ? '/adopter/header.php' : '/header.php');
render_page_hero('Compassion in action, since 2010', 'We rescue, treat and rehome animals that have no voice of their own — and make adopting them simple and transparent.', 'img/16.jpg', 'About us',
    [['Home', url($isAdopter ? 'adopter/home.php' : 'index.php')]]);
?>
<section class="section pt-4">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-animate>
                <div class="about-collage">
                    <img src="<?= e(url('img/18.jpg')) ?>" alt="Our team with rescued animals at the shelter" loading="lazy">
                    <img src="<?= e(url('img/12.jpg')) ?>" alt="A vet examining a rescued dog" loading="lazy">
                    <img src="<?= e(url('img/5.jpg')) ?>" alt="Rescued dogs at feeding time" loading="lazy">
                    <div class="badge-year"><strong>2010</strong>caring since</div>
                </div>
            </div>
            <div class="col-lg-6" data-animate>
                <span class="eyebrow">Our story</span>
                <h2 class="display-font" style="font-size:clamp(2rem,3.4vw,2.9rem)">Giving voiceless animals a <span class="highlight">second chance</span></h2>
                <p class="fs-5 text-muted mt-3">Animal Matter to Me (AMTM) bridges the gap between rescued animals and compassionate adopters. Backed by years of animal welfare work since 2010, we promote responsible adoption while putting animal health, safety and well-being first.</p>
                <p>From neutering to vaccination drives and emergency rescue operations, our team is committed to the welfare and dignity of every animal in our care. Every animal we save is a life that matters to us.</p>
                <ul class="value-list">
                    <li><i class="bi bi-check-lg"></i> Every pet is health-checked, and vaccinated where possible</li>
                    <li><i class="bi bi-check-lg"></i> Responsible, transparent adoption you can follow online</li>
                    <li><i class="bi bi-check-lg"></i> Branches you can visit to meet your pet in person</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="bento-stat rounded-5" data-animate style="border-radius:var(--radius-xl)">
            <div><div class="big-num"><?= $liveStats['available'] ?></div><span>Pets waiting for a home today</span></div>
            <div><div class="big-num"><?= $liveStats['adopted'] ?></div><span>Adoptions completed on this portal</span></div>
            <div><div class="big-num"><?= $liveStats['branches'] ?></div><span><?= $liveStats['branches'] === 1 ? 'Branch' : 'Branches' ?> you can visit</span></div>
        </div>
    </div>
</section>

<section class="section" id="process">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">How adoption works</span>
            <h2>From first look to forever home</h2>
            <p>No surprises: you can see where your application is at every step, and we email you whenever something changes.</p>
        </div>
        <?php render_adoption_steps(true); ?>
        <div class="row g-3 mt-4">
            <div class="col-md-4" data-animate><div class="info-panel h-100"><h3><i class="bi bi-person-vcard text-brand"></i> What you need</h3><p class="mb-0 text-muted">A free account, your CNIC, a phone number and your home address.</p></div></div>
            <div class="col-md-4" data-animate><div class="info-panel h-100"><h3><i class="bi bi-hourglass-split text-brand"></i> While we review</h3><p class="mb-0 text-muted">The pet is reserved for you — nobody else can apply until we decide.</p></div></div>
            <div class="col-md-4" data-animate><div class="info-panel h-100"><h3><i class="bi bi-calendar-check text-brand"></i> After approval</h3><p class="mb-0 text-muted">Pick your pet up from the branch within 3 days. Bring your CNIC with you.</p></div></div>
        </div>
    </div>
</section>

<section class="section pt-0">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Our team</span>
            <h2>The people behind every match</h2>
            <p>They rescue, treat, feed and match every animal with the right family.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($team as [$photo, $name, $role]): ?>
                <div class="col-6 col-md-4 col-lg-2" data-animate>
                    <div class="team-card">
                        <img src="<?= e(url($photo)) ?>" alt="<?= e($name) ?>" loading="lazy"<?= img_fallback() ?>>
                        <h3><?= e($name) ?></h3>
                        <span><?= e($role) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="cta-band text-center" data-animate>
            <h2>Meet the pets waiting for you</h2>
            <p class="mb-4">Your new best friend could be at one of our branches right now.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-white btn-lg" href="<?= e(url($base . 'pets.php')) ?>"><i class="bi bi-search-heart"></i> Browse pets</a>
                <a class="btn btn-glass btn-lg" href="<?= e(url($base . 'contactUs.php')) ?>">Contact us</a>
            </div>
        </div>
    </div>
</section>
<?php page_end(); ?>
