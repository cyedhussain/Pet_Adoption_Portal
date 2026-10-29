<?php
require_once __DIR__ . '/dbconnect.php';
redirect_if_logged_in();

$values = ['name' => '', 'cnic' => '', 'email' => ''];
$errors = [];
if (is_post()) {
    $values = ['name' => post('name'), 'cnic' => preg_replace('/[\s-]/', '', post('cnic')), 'email' => post('email')];
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!preg_match('/^\d{13}$/', $values['cnic'])) {
        $errors['cnic'] = 'Your CNIC must be exactly 13 digits.';
    } elseif (db_value("SELECT COUNT(*) FROM `adopter` WHERE `cnic` = ?", [$values['cnic']])) {
        $errors['cnic'] = 'This CNIC is already registered.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (db_value("SELECT COUNT(*) FROM `adopter` WHERE `email` = ?", [$values['email']])
           || db_value("SELECT COUNT(*) FROM `admin` WHERE `email` = ?", [$values['email']])) {
        $errors['email'] = 'This email is already registered. Try logging in instead.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The passwords don’t match.';
    }

    if (!$errors) {
        db_run(
            "INSERT INTO `adopter` (`name`, `cnic`, `email`, `password`) VALUES (?, ?, ?, ?)",
            [$values['name'], $values['cnic'], $values['email'], hash_password($password)]
        );
        login_user('adopter', db_insert_id(), $values['name']);
        flash('Welcome to ' . APP_NAME . ', ' . $values['name'] . '! Start by finding a pet you love.');
        redirect('adopter/home.php');
    }
}

function field_error($errors, $key)
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

$pageTitle = 'Create an account';
include __DIR__ . '/header.php';
?>
<div class="auth-shell">
    <div class="auth-main">
        <div class="auth-box reveal">
            <span class="eyebrow">Become an adopter</span>
            <h1>Create your free account</h1>
            <p class="lead-muted">It takes less than a minute. Already registered? <a class="fw-bold" href="<?= e(url('login.php')) ?>">Log in</a></p>
            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span>Please fix the highlighted fields.</span></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="name">Full name</label>
                    <div class="input-icon"><i class="bi bi-person"></i>
                        <input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" value="<?= e($values['name']) ?>" maxlength="100" placeholder="Your name" autocomplete="name" required autofocus>
                    </div>
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-icon"><i class="bi bi-envelope"></i>
                            <input class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($values['email']) ?>" placeholder="you@example.com" autocomplete="email" required>
                        </div>
                        <?= field_error($errors, 'email') ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="cnic">CNIC</label>
                        <div class="input-icon"><i class="bi bi-person-vcard"></i>
                            <input class="form-control<?= isset($errors['cnic']) ? ' is-invalid' : '' ?>" id="cnic" name="cnic" value="<?= e($values['cnic']) ?>" inputmode="numeric" pattern="\d{13}" maxlength="15" placeholder="13 digits" aria-describedby="cnicHelp" required>
                        </div>
                        <?= field_error($errors, 'cnic') ?>
                    </div>
                    <div class="col-12 mt-1"><div class="form-text" id="cnicHelp">We use your CNIC to verify adoptions. Bring the card when you pick up your pet.</div></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon"><i class="bi bi-lock"></i>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" aria-describedby="strengthText" required>
                        <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="strength" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <div class="form-text" id="strengthText">Use 8+ characters with a mix of letters, numbers and symbols.</div>
                    <?= field_error($errors, 'password') ?>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="confirm">Confirm password</label>
                    <div class="input-icon"><i class="bi bi-lock-fill"></i>
                        <input class="form-control<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>" type="password" id="confirm" name="confirm" placeholder="Type it again" autocomplete="new-password" required>
                    </div>
                    <?= field_error($errors, 'confirm') ?>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Create account <i class="bi bi-arrow-right"></i></button>
            </form>
            <ul class="value-list small">
                <li><i class="bi bi-check-lg"></i> Save pets to your favourites</li>
                <li><i class="bi bi-check-lg"></i> Apply online and track every step</li>
            </ul>
        </div>
    </div>
    <?php render_auth_art('Adopt, don’t shop', 'Give a rescued animal a home for life.', 'Create a free account to save favourites, apply to adopt and track your application from review to pickup.', 'img/10.jpg'); ?>
</div>
<script>
    (function () {
        var input = document.getElementById('password'), bars = document.querySelectorAll('.strength span'), text = document.getElementById('strengthText');
        var levels = [['#c9372c', 'Too short'], ['#c9372c', 'Weak'], ['#b7791f', 'Okay'], ['#119174', 'Good'], ['#12614f', 'Strong']];
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 8) score++;
            if (v.length >= 12) score++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
            if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) score++;
            if (v.length < 8) score = 0;
            bars.forEach(function (b, i) { b.style.background = v && i < Math.max(1, score) ? levels[score][0] : ''; });
            text.textContent = v ? 'Password strength: ' + levels[score][1] : 'Use 8+ characters with a mix of letters, numbers and symbols.';
        });
    })();
</script>
<?php page_end(); ?>
