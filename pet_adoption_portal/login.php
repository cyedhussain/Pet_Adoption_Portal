<?php
require_once __DIR__ . '/dbconnect.php';
redirect_if_logged_in();

// Where to go after logging in, e.g. adopter/petInfo.php?pindex=3. Only paths inside the app are allowed.
$next = query_param('next', post('next'));
if (!preg_match('#^(adopter|admin)/[A-Za-z]+\.php(\?[\w=&%-]*)?$#', $next)) {
    $next = '';
}

$email = '';
$error = '';
if (is_post()) {
    $email = post('email');
    $password = $_POST['password'] ?? '';

    $adopter = db_one("SELECT * FROM `adopter` WHERE `email` = ?", [$email]);
    $admin = $adopter ? null : db_one("SELECT * FROM `admin` WHERE `email` = ?", [$email]);

    if ($adopter && verify_password($password, $adopter['password'], 'adopter', 'adopter_id', $adopter['adopter_id'])) {
        login_user('adopter', $adopter['adopter_id'], $adopter['name']);
        flash('Welcome back, ' . $adopter['name'] . '!');
        redirect(str_starts_with($next, 'adopter/') ? $next : 'adopter/home.php');
    }
    if ($admin && verify_password($password, $admin['password'], 'admin', 'admin_id', $admin['admin_id'])) {
        login_user('admin', $admin['admin_id'], $admin['name']);
        flash('Welcome back, ' . $admin['name'] . '!');
        redirect(str_starts_with($next, 'admin/') ? $next : 'admin/home.php');
    }
    $error = 'That email and password don’t match an account.';
}

$pageTitle = 'Log in';
include __DIR__ . '/header.php';
?>
<div class="auth-shell">
    <div class="auth-main">
        <div class="auth-box reveal">
            <span class="eyebrow">Welcome back</span>
            <h1>Log in to your account</h1>
            <p class="lead-muted">Adopters and shelter staff both sign in here.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon-fill"></i><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-icon"><i class="bi bi-envelope"></i>
                        <input class="form-control form-control-lg<?= $error ? ' is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required autofocus>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <label class="form-label" for="password">Password</label>
                        <a class="small fw-bold" href="<?= e(url('forgetPassword.php')) ?>">Forgot password?</a>
                    </div>
                    <div class="input-icon"><i class="bi bi-lock"></i>
                        <input class="form-control form-control-lg<?= $error ? ' is-invalid' : '' ?>" type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Log in <i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="divider">New to Animal Matter?</div>
            <a class="btn btn-outline-primary btn-lg w-100" href="<?= e(url('signup.php')) ?>"><i class="bi bi-person-plus"></i> Create an adopter account</a>
        </div>
    </div>
    <?php render_auth_art('Someone is waiting', 'Your new best friend might be one click away.', 'Log in to save your favourite pets, apply to adopt and follow every step of your application.'); ?>
</div>
<?php page_end(); ?>
