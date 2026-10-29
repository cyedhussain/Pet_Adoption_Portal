<?php
require_once __DIR__ . '/dbconnect.php';

$values = ['name' => '', 'email' => '', 'message' => ''];
if (current_role() === 'adopter') {
    $me = db_one("SELECT `name`, `email` FROM `adopter` WHERE `adopter_id` = ?", [current_user_id()]);
    if ($me) {
        $values['name'] = $me['name'];
        $values['email'] = $me['email'];
    }
}

$errors = [];
if (is_post()) {
    $values = ['name' => post('name'), 'email' => post('email'), 'message' => post('message')];
    if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Please enter your name (up to 100 characters).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (mb_strlen($values['message']) < 5 || mb_strlen($values['message']) > 500) {
        $errors['message'] = 'Your message should be between 5 and 500 characters.';
    }
    if (!$errors) {
        $duplicate = db_value(
            "SELECT COUNT(*) FROM `contact` WHERE `email` = ? AND `message` = ?",
            [$values['email'], $values['message']]
        );
        if (!$duplicate) {
            db_run(
                "INSERT INTO `contact` (`name`, `email`, `message`, `answer`) VALUES (?, ?, ?, '')",
                [$values['name'], $values['email'], $values['message']]
            );
        }
        flash('Thanks, ' . $values['name'] . '! Your message has been sent. We will reply by email.');
        redirect('contactUs.php');
    }
}

$locations = all_locations();

$pageTitle = 'Contact us';
$activeNav = 'contact';
include __DIR__ . '/header.php';
render_page_hero('We’d love to hear from you', 'Questions about a pet, an application or volunteering? Send us a message or visit one of our branches.', 'img/10.jpg', 'Contact us', [['Home', url('index.php')]]);
?>
<section class="pb-5">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-7">
                <div class="form-card" data-animate>
                    <span class="eyebrow">Send a message</span>
                    <h2>How can we help?</h2>
                    <p class="text-muted mb-4">We usually reply within one working day, by email.<?php if (!current_role()): ?> Adopters who <a class="fw-bold" href="<?= e(url('login.php?next=' . rawurlencode('adopter/contactUs.php'))) ?>">log in</a> can also read replies on their account.<?php endif; ?></p>
                    <?php if ($errors): ?>
                        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
                    <?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Full name</label>
                                <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= e($values['name']) ?>" maxlength="100" autocomplete="name" required>
                                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($values['email']) ?>" autocomplete="email" required>
                                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="message">Message</label>
                                <textarea class="form-control<?= isset($errors['message']) ? ' is-invalid' : '' ?>" id="message" name="message" rows="6" maxlength="500" required placeholder="Tell us which pet or branch your question is about…" data-count="#messageCount"><?= e($values['message']) ?></textarea>
                                <?php if (isset($errors['message'])): ?><div class="invalid-feedback"><?= e($errors['message']) ?></div><?php endif; ?>
                                <div class="form-text d-flex justify-content-between"><span>Between 5 and 500 characters.</span><span class="char-count" id="messageCount"></span></div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg mt-4" type="submit"><i class="bi bi-send"></i> Send message</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <span class="eyebrow">Our branches</span>
                <h2 class="display-font mb-4" style="font-size:clamp(1.7rem,2.6vw,2.2rem)">Come and say hello</h2>
                <?php if (!$locations): ?>
                    <?php render_empty_state('bi-geo-alt', 'Branches coming soon', 'Branch details will appear here soon.', '', '', true); ?>
                <?php endif; ?>
                <div class="d-grid gap-3">
                    <?php foreach ($locations as $l): ?>
                        <div class="contact-tile" data-animate>
                            <i class="bi bi-geo-alt"></i>
                            <div>
                                <h3><?= e($l['city']) ?></h3>
                                <p><?= e($l['address']) ?></p>
                                <p class="mt-1"><a href="tel:<?= e($l['contact']) ?>"><i class="bi bi-telephone"></i> <?= e($l['contact']) ?></a> · <a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php page_end(); ?>
