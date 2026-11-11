<main class="auth-shell">
    <section class="form-card" aria-labelledby="page-heading">
        <a class="back-link" href="/login"><span aria-hidden="true">←</span> Back to login</a>
        <p class="eyebrow">Account access</p>
        <h1 id="page-heading">Forgot your password?</h1>
        <p class="form-intro">Enter your account email and we’ll send a secure link if it matches an account.</p>
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
        <form action="/forgot-password" method="post" class="auth-form" data-submit-loading>
            <?= csrf_field() ?>
            <?php
            $fieldId = 'email';
            $fieldName = 'email';
            $fieldLabel = 'Email address';
            $fieldType = 'email';
            $fieldAutocomplete = 'email';
            $fieldMaxLength = 254;
            $fieldValue = (string) ($oldInput['email'] ?? $email);
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'form-field.php';
            ?>
            <?php if (isset($errors['email'])): ?>
                <p class="field-error" role="alert"><?= e($errors['email']) ?></p>
            <?php endif; ?>
            <?php
            $buttonLabel = 'Send reset link';
            $buttonClass = 'button button-primary form-submit';
            $buttonType = 'submit';
            $buttonDisabled = false;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
            ?>
        </form>
        <p class="form-footer">Remembered it? <a href="/login">Log in</a></p>
    </section>
</main>
