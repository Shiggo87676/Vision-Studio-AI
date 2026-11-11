<main class="form-page">
    <section class="form-card account-card" aria-labelledby="page-heading">
        <a class="back-link" href="/dashboard"><span aria-hidden="true">←</span> Back to dashboard</a>
        <p class="eyebrow">Account management</p>
        <h1 id="page-heading">Your profile</h1>
        <p class="form-intro">Review your account details and update your name.</p>
        <?php if ($successMessage !== ''): ?>
            <?php
            $notificationType = 'success';
            $notificationMessage = $successMessage;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
            ?>
        <?php endif; ?>
        <dl class="profile-details">
            <div>
                <dt>Full name</dt>
                <dd><?= e((string) $currentUser['name']) ?></dd>
            </div>
            <div>
                <dt>Email address</dt>
                <dd><?= e((string) $currentUser['email']) ?></dd>
            </div>
            <div>
                <dt>Account created</dt>
                <dd><?= e((new DateTimeImmutable((string) $currentUser['created_at']))->format('F j, Y')) ?></dd>
            </div>
            <div>
                <dt>Account status</dt>
                <dd><span class="status-badge"><?= e(ucfirst((string) $currentUser['status'])) ?></span></dd>
            </div>
        </dl>
        <p class="profile-note">Email address changes require verification before they can take effect.</p>
        <?php if (isset($errors['form'])): ?>
            <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
        <?php endif; ?>
        <form action="/profile" method="post" class="auth-form" data-submit-loading>
            <?= csrf_field() ?>
            <label class="form-field" for="name">
                <span>Full name</span>
                <input id="name" name="name" type="text" value="<?= e((string) ($oldInput['name'] ?? $currentUser['name'])) ?>"
                    autocomplete="name" maxlength="120" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" required>
            </label>
            <?php if (isset($errors['name'])): ?>
                <p class="field-error" role="alert"><?= e($errors['name']) ?></p>
            <?php endif; ?>
            <?php
            $buttonLabel = 'Save profile';
            $buttonClass = 'button button-primary form-submit';
            $buttonType = 'submit';
            $buttonDisabled = false;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
            ?>
        </form>
        <p class="form-footer"><a href="/change-password">Change your password</a></p>
    </section>
</main>
