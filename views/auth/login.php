<main class="form-page">
    <section class="form-card" aria-labelledby="page-heading">
        <p class="eyebrow">Welcome back</p>
        <h1 id="page-heading">Log in</h1>
        <p class="form-intro">Sign in to your Vision Studio AI account.</p>
        <?php if ($successMessage !== ''): ?>
            <?php
            $notificationType = 'success';
            $notificationMessage = $successMessage;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
            ?>
        <?php endif; ?>
        <?php if (isset($errors['form'])): ?>
            <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
        <?php endif; ?>
        <form action="/login" method="post" class="auth-form">
            <?= csrf_field() ?>
            <?php
            $fieldId = 'email';
            $fieldName = 'email';
            $fieldLabel = 'Email';
            $fieldType = 'email';
            $fieldAutocomplete = 'username';
            $fieldMaxLength = 254;
            $fieldValue = (string) ($oldInput['email'] ?? '');
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'form-field.php';
            ?>
            <label for="password">Password</label>
            <div class="password-input">
                <input id="password" name="password" type="password" maxlength="72"
                    autocomplete="current-password" required>
                <button class="password-toggle" type="button" data-password-toggle="password"
                    aria-label="Show password" aria-controls="password" aria-pressed="false">Show</button>
            </div>
            <a class="forgot-link" href="/forgot-password">Forgot password?</a>
            <label class="remember-me" for="remember_me">
                <input id="remember_me" name="remember_me" type="checkbox" value="1"
                    <?= $rememberMe ? 'checked' : '' ?>>
                <span>Remember me for 90 days</span>
            </label>
            <?php
            $buttonLabel = 'Log in';
            $buttonClass = 'button button-primary form-submit';
            $buttonType = 'submit';
            $buttonDisabled = false;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
            ?>
        </form>
        <p class="form-footer">New here? <a href="/register">Create an account</a></p>
    </section>
</main>
