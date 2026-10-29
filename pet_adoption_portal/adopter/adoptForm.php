<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();

$petId = query_int('pindex');
$pet = find_pet($petId);
if (!$pet || (int) $pet['requested'] !== 0) {
    flash($pet ? $pet['name'] . ' already has an application in progress.' : 'That pet could not be found.', 'warning');
    redirect($pet ? 'adopter/petInfo.php?pindex=' . $petId : 'adopter/pets.php');
}
$adopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);

$values = ['name' => $adopter['name'], 'contact' => '', 'address' => ''];
$errors = [];
if (is_post()) {
    $values = ['name' => post('name'), 'contact' => post('contact'), 'address' => post('address')];

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!preg_match('/^\+?[0-9][0-9\s-]{6,18}$/', $values['contact'])) {
        $errors['contact'] = 'Please enter a valid phone number, e.g. 0543-1234567.';
    }
    if (mb_strlen($values['address']) < 5 || mb_strlen($values['address']) > 200) {
        $errors['address'] = 'Please enter your full address (5–200 characters).';
    }
    if (!post('agree')) {
        $errors['agree'] = 'Please confirm you can pick the pet up within 3 days of approval.';
    }

    if (!$errors) {
        $created = db_transaction(function () use ($petId, $adopterId, $adopter, $values) {
            $locked = db_one("SELECT `location_id`, `requested` FROM `pets` WHERE `pet_id` = ? FOR UPDATE", [$petId]);
            if (!$locked || (int) $locked['requested'] !== 0) {
                return false;
            }
            db_run(
                "INSERT INTO `requests` (`name`, `email`, `contact`, `cnic`, `address`, `pet_id`, `location_id`, `adopter_id`, `status`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
                [$values['name'], $adopter['email'], $values['contact'], (string) $adopter['cnic'], $values['address'],
                 $petId, (int) $locked['location_id'], $adopterId]);
            db_run("UPDATE `pets` SET `requested` = 1 WHERE `pet_id` = ?", [$petId]);
            return true;
        });

        if (!$created) {
            flash('Sorry, someone applied for ' . $pet['name'] . ' just before you.', 'warning');
            redirect('adopter/petInfo.php?pindex=' . $petId);
        }

        send_mail($adopter['email'], 'We received your application for ' . $pet['name'], mail_template(
            'Application received',
            '<p>Hi ' . e($values['name']) . ',</p><p>Thank you for applying to adopt <strong>' . e($pet['name'])
            . '</strong> from our ' . e($pet['city']) . ' branch. Our team will review your application and email you as soon as there is news.</p>',
            'Track your application', absolute_url('adopter/requested.php')));

        flash('Application sent! We’ll let you know as soon as the shelter has reviewed it.');
        redirect('adopter/requested.php');
    }
}

$invalid = fn($field) => isset($errors[$field]) ? ' is-invalid' : '';
$pageTitle = 'Adopt ' . $pet['name'];
$activeNav = 'pets';
include __DIR__ . '/header.php';
?>
<section class="pet-detail">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('adopter/pets.php')) ?>">Find a pet</a><i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <a href="<?= e(url('adopter/petInfo.php?pindex=' . $petId)) ?>"><?= e($pet['name']) ?></a><i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <span class="current" aria-current="page">Application</span>
        </nav>
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="form-card reveal">
                    <ol class="stepper" aria-label="Adoption steps">
                        <li class="is-current"><span class="n">1</span>Apply</li>
                        <li><span class="n">2</span>Shelter review</li>
                        <li><span class="n">3</span>Pick up</li>
                    </ol>
                    <span class="eyebrow">Adoption application</span>
                    <h1 class="h2 break-word">Give <?= e($pet['name']) ?> a home</h1>
                    <p class="text-muted mb-4">Tell us how to reach you. Your email and CNIC come from your account. While we review, <?= e($pet['name']) ?> is reserved for you.</p>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="form-section">
                            <h2 class="form-section-title"><span class="n">1</span> About you</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="name">Full name</label>
                                    <input class="form-control<?= $invalid('name') ?>" id="name" name="name" value="<?= e($values['name']) ?>" maxlength="100" autocomplete="name" required>
                                    <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="email">Email</label>
                                    <div class="input-icon"><i class="bi bi-envelope"></i><input class="form-control" id="email" value="<?= e($adopter['email']) ?>" readonly disabled></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cnic">CNIC</label>
                                    <div class="input-icon"><i class="bi bi-person-vcard"></i><input class="form-control" id="cnic" value="<?= e($adopter['cnic']) ?>" readonly disabled></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h2 class="form-section-title"><span class="n">2</span> How we reach you</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="contact">Phone number</label>
                                    <div class="input-icon"><i class="bi bi-telephone"></i>
                                        <input class="form-control<?= $invalid('contact') ?>" id="contact" name="contact" value="<?= e($values['contact']) ?>" placeholder="0543-1234567" maxlength="20" inputmode="tel" autocomplete="tel" required>
                                    </div>
                                    <div class="invalid-feedback d-block"><?= e($errors['contact'] ?? '') ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="address">Home address</label>
                                    <textarea class="form-control<?= $invalid('address') ?>" id="address" name="address" rows="3" maxlength="200" placeholder="Street, area, city" autocomplete="street-address" data-count="#addressCount" required><?= e($values['address']) ?></textarea>
                                    <div class="invalid-feedback"><?= e($errors['address'] ?? '') ?></div>
                                    <div class="form-text text-end char-count" id="addressCount"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h2 class="form-section-title"><span class="n">3</span> Pickup</h2>
                            <div class="form-check p-3 rounded-4 ps-5" style="background:var(--bg)">
                                <input class="form-check-input<?= $invalid('agree') ?>" type="checkbox" id="agree" name="agree" value="1"<?= is_post() && post('agree') ? ' checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="agree">I can visit the <?= e($pet['city']) ?> branch to pick <?= e($pet['name']) ?> up within 3 days of approval, bringing my CNIC.</label>
                                <div class="invalid-feedback"><?= e($errors['agree'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send"></i> Send application</button>
                            <a class="btn btn-link" href="<?= e(url('adopter/petInfo.php?pindex=' . $petId)) ?>">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="adopt-panel p-0 overflow-hidden reveal reveal-2">
                    <img src="<?= e(pet_image_url($pet['image'])) ?>" alt="<?= e($pet['name']) ?>" class="w-100" style="aspect-ratio:4/3;object-fit:cover"<?= img_fallback() ?>>
                    <div class="p-4">
                        <span class="eyebrow"><?= e($pet['category_name']) ?></span>
                        <h2 class="mb-1 break-word"><?= e($pet['name']) ?></h2>
                        <?php if ($pet['breed'] !== ''): ?><p class="text-muted fw-semibold break-word"><?= e($pet['breed']) ?></p><?php endif; ?>
                        <div class="chips mb-3"><?= pet_badges($pet) ?></div>
                        <div class="info-row"><i class="bi bi-geo-alt"></i><div><small>Pick up from</small><?= e($pet['city']) ?> — <?= e($pet['location_address']) ?></div></div>
                        <div class="info-row"><i class="bi bi-telephone"></i><div><small>Branch phone</small><?= e($pet['location_contact']) ?></div></div>
                        <ol class="tracker mb-0" aria-label="What happens next">
                            <li class="is-next"><span class="tracker-dot"></span><span class="tracker-label">Apply</span><span class="tracker-time">Today</span></li>
                            <li><span class="tracker-dot"></span><span class="tracker-label">Review</span><span class="tracker-time">By the shelter</span></li>
                            <li><span class="tracker-dot"></span><span class="tracker-label">Pick up</span><span class="tracker-time">Within 3 days</span></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php page_end(); ?>
