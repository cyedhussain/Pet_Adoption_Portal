<?php
require_once __DIR__ . '/dbconnect.php';

$token = query_param('token');
$reset = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $reset = db_one(
        "SELECT * FROM `resetpassword` WHERE `randToken` = ? AND `created_at` >= NOW() - INTERVAL 1 HOUR",
        [hash('sha256', $token)]
    );
}

$errors = [];
if ($reset && is_post()) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The passwords don’t match.';
    }
    if (!$errors) {
        $isAdmin = $reset['user'] === 'admin';
        db_transaction(function () use ($reset, $password, $isAdmin) {
            db_run(
                $isAdmin
                    ? "UPDATE `admin` SET `password` = ? WHERE `admin_id` = ?"
                    : "UPDATE `adopter` SET `password` = ? WHERE `adopter_id` = ?",
                [hash_password($password), (int) $reset['user_id']]
            );
            db_run("DELETE FROM `resetpassword` WHERE `reset_id` = ?", [(int) $reset['reset_id']]);
        });
        flash('Your password has been changed. You can log in now.');
        redirect('login.php');
    }
}

$pageTitle = 'Reset password';
include __DIR__ . '/header.php';
?>
<?php if (!$reset): ?>
    <section class="section">
        <div class="container" style="max-width:640px">
            <?php render_empty_state('bi-link-45deg', 'This link has expired', 'Password reset links work once and only for 1 hour. Please request a new one.', 'Request a new link', url('forgetPassword.php')); ?>
        </div>
    </section>
<?php else: ?>
<div class="auth-shell">
    <div class="auth-main">
        <div class="auth-box reveal">
            <span class="eyebrow">Account help</span>
            <h1>Choose a new password</h1>
            <p class="lead-muted">Use at least 8 characters — ideally one you haven’t used before.</p>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <div class="input-icon"><i class="bi bi-lock"></i>
                        <input class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
                        <button type="button" class="password-toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye"></i></button>
                    </div>
                    <?php if (isset($errors['password'])): ?><div class="invalid-feedback d-block"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm">Confirm new password</label>
                    <div class="input-icon"><i class="bi bi-lock-fill"></i>
                        <input class="form-control<?= isset($errors['confirm']) ? ' is-invalid' : '' ?>" type="password" id="confirm" name="confirm" autocomplete="new-password" required>
                    </div>
                    <?php if (isset($errors['confirm'])): ?><div class="invalid-feedback d-block"><?= e($errors['confirm']) ?></div><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit"><i class="bi bi-check2"></i> Save new password</button>
            </form>
        </div>
    </div>
    <?php render_auth_art('Almost there', 'One more step and you’re back.', 'After saving, log in with your new password.', 'img/4.jpg'); ?>
</div>
<?php endif; ?>
<?php page_end(); ?>
