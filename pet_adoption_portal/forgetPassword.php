<?php
require_once __DIR__ . '/dbconnect.php';
redirect_if_logged_in();

$email = '';
$error = '';
if (is_post()) {
    $email = post('email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $user = db_one("SELECT `adopter_id` AS id, `name`, 'adopter' AS kind FROM `adopter` WHERE `email` = ?", [$email])
             ?: db_one("SELECT `admin_id` AS id, `name`, 'admin' AS kind FROM `admin` WHERE `email` = ?", [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db_run("DELETE FROM `resetpassword` WHERE `user_id` = ? AND `user` = ?", [(int) $user['id'], $user['kind']]);
            db_run(
                "INSERT INTO `resetpassword` (`randToken`, `user_id`, `user`) VALUES (?, ?, ?)",
                [hash('sha256', $token), (int) $user['id'], $user['kind']]
            );
            $link = absolute_url('resetPassword.php?token=' . $token);
            send_mail($email, 'Reset your password', mail_template(
                'Reset your password',
                '<p>Hi ' . e($user['name']) . ',</p><p>We received a request to reset your password. The link below works for 1 hour. If you didn’t ask for this, you can ignore this email.</p>',
                'Choose a new password',
                $link
            ));
            if (!mail_is_configured()) {
                flash('Dev only: email is not configured, so here is the reset link: ' . $link, 'info');
            }
        }
        // Same message whether or not the account exists, so this form can't be used to find accounts.
        flash('If an account exists for ' . $email . ', we have sent a link to reset your password. It expires in 1 hour.');
        redirect('forgetPassword.php');
    }
}

$pageTitle = 'Forgot password';
include __DIR__ . '/header.php';
?>
<div class="auth-shell">
    <div class="auth-main">
        <div class="auth-box reveal">
            <span class="eyebrow">Account help</span>
            <h1>Forgot your password?</h1>
            <p class="lead-muted">Enter the email you signed up with and we’ll send you a link to choose a new one.</p>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-icon"><i class="bi bi-envelope"></i>
                        <input class="form-control form-control-lg<?= $error ? ' is-invalid' : '' ?>" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@example.com" autocomplete="email" required autofocus>
                    </div>
                    <?php if ($error): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit"><i class="bi bi-send"></i> Send reset link</button>
            </form>
            <p class="note mt-4"><i class="bi bi-clock-history"></i><span>The link works once and expires after 1 hour.</span></p>
            <div class="divider">Remembered it?</div>
            <a class="btn btn-outline-primary btn-lg w-100" href="<?= e(url('login.php')) ?>"><i class="bi bi-arrow-left"></i> Back to log in</a>
        </div>
    </div>
    <?php render_auth_art('It happens to the best of us', 'We’ll get you back in no time.', 'Once you’re logged in again you can pick up right where you left off.', 'img/4.jpg'); ?>
</div>
<?php page_end(); ?>
