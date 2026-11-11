<main class="auth-shell">
    <section class="form-card" aria-labelledby="page-heading">
        <a class="back-link" href="/login"><span aria-hidden="true">←</span> Back to login</a>
        <p class="eyebrow">Account verification</p>
        <?php if ($emailVerificationTokenValid): ?>
            <h1 id="page-heading">Confirm your email</h1>
            <p class="form-intro">Confirm this address to activate your Vision Studio AI account.</p>
            <form action="/verify-email" method="post" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <?php
                $buttonLabel = 'Verify email address';
                $buttonClass = 'button button-primary form-submit';
                $buttonType = 'submit';
                $buttonDisabled = false;
                require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
                ?>
            </form>
        <?php else: ?>
            <h1 id="page-heading">Verify your email</h1>
            <p class="form-intro">Enter the address you used to register. If it belongs to an unverified account, we’ll send a secure verification link.</p>
            <?php if ($successMessage !== ''): ?>
                <?php
                $notificationType = 'success';
                $notificationMessage = $successMessage;
                require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
                ?>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
                <p class="form-error" role="alert"><?= e($message) ?></p>
            <?php endif; ?>
            <?php if (isset($errors['form'])): ?>
                <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
            <?php endif; ?>
            <form action="/verify-email/resend" method="post" class="auth-form" data-submit-loading>
                <?= csrf_field() ?>
                <?php
                $fieldId = 'email';
                $fieldName = 'email';
                $fieldLabel = 'Email address';
                $fieldType = 'email';
                $fieldAutocomplete = 'email';
                $fieldMaxLength = 254;
                $fieldValue = (string) ($oldInput['email'] ?? '');
                require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'form-field.php';
                ?>
                <?php
                $buttonLabel = 'Send verification link';
                $buttonClass = 'button button-primary form-submit';
                $buttonType = 'submit';
                $buttonDisabled = false;
                require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
                ?>
            </form>
        <?php endif; ?>
        <p class="form-footer">Already verified? <a href="/login">Log in</a></p>
    </section>
</main>
