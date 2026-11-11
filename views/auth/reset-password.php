<main class="auth-shell">
    <section class="form-card" aria-labelledby="page-heading">
        <a class="back-link" href="/forgot-password"><span aria-hidden="true">←</span> Back to password help</a>
        <p class="eyebrow">A fresh start</p>
        <h1 id="page-heading">Choose a new password</h1>
        <p class="form-intro">Set a new password for your Vision Studio AI account.</p>
        <?php if (!$resetTokenValid): ?>
            <?php
            $notificationType = 'error';
            $notificationMessage = $message !== ''
                ? $message
                : 'This reset link is invalid, expired, or has already been used. Request a new link to continue.';
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
            ?>
            <p class="form-footer"><a href="/forgot-password">Request a new reset link</a></p>
        <?php else: ?>
            <?php if (isset($errors['form'])): ?>
                <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
            <?php endif; ?>
            <form action="/reset-password" method="post" class="auth-form" data-submit-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label for="password">New password</label>
                <div class="password-input">
                    <input id="password" name="password" type="password" autocomplete="new-password"
                        minlength="7" maxlength="72" aria-describedby="password-requirement" required>
                    <button class="password-toggle" type="button" data-password-toggle="password"
                        aria-label="Show password" aria-controls="password" aria-pressed="false">Show</button>
                </div>
                <p class="password-guidance" id="password-requirement">Use at least 7 characters and no more than 72 bytes.</p>
                <?php if (isset($errors['password'])): ?>
                    <p class="field-error" role="alert"><?= e($errors['password']) ?></p>
                <?php endif; ?>
                <label for="password_confirmation">Confirm new password</label>
                <div class="password-input">
                    <input id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" minlength="7" maxlength="72" required>
                    <button class="password-toggle" type="button" data-password-toggle="password_confirmation"
                        aria-label="Show confirmation password" aria-controls="password_confirmation"
                        aria-pressed="false">Show</button>
                </div>
                <?php if (isset($errors['password_confirmation'])): ?>
                    <p class="field-error" role="alert"><?= e($errors['password_confirmation']) ?></p>
                <?php endif; ?>
                <?php
                $buttonLabel = 'Update password';
                $buttonClass = 'button button-primary form-submit';
                $buttonType = 'submit';
                $buttonDisabled = false;
                require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
                ?>
            </form>
        <?php endif; ?>
        <p class="form-footer">Need a reset link? <a href="/forgot-password">Password help</a></p>
    </section>
</main>
