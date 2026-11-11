<main class="form-page">
    <section class="form-card" aria-labelledby="page-heading">
        <a class="back-link" href="/profile"><span aria-hidden="true">←</span> Back to profile</a>
        <p class="eyebrow">Account security</p>
        <h1 id="page-heading">Change password</h1>
        <p class="form-intro">Verify your current password before choosing a new one.</p>
        <?php if (isset($errors['form'])): ?>
            <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
        <?php endif; ?>
        <form action="/change-password" method="post" class="auth-form" data-submit-loading>
            <?= csrf_field() ?>
            <label for="current_password">Current password</label>
            <div class="password-input">
                <input id="current_password" name="current_password" type="password"
                    autocomplete="current-password" maxlength="72" required>
                <button class="password-toggle" type="button" data-password-toggle="current_password"
                    aria-label="Show current password" aria-controls="current_password" aria-pressed="false">Show</button>
            </div>
            <?php if (isset($errors['current_password'])): ?>
                <p class="field-error" role="alert"><?= e($errors['current_password']) ?></p>
            <?php endif; ?>
            <label for="password">New password</label>
            <div class="password-input">
                <input id="password" name="password" type="password" autocomplete="new-password"
                    minlength="7" maxlength="72" aria-describedby="password-requirement" required>
                <button class="password-toggle" type="button" data-password-toggle="password"
                    aria-label="Show new password" aria-controls="password" aria-pressed="false">Show</button>
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
                    aria-label="Show password confirmation" aria-controls="password_confirmation"
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
    </section>
</main>
