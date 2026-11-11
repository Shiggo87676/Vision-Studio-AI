<main class="form-page">
    <section class="form-card" aria-labelledby="page-heading">
        <p class="eyebrow">Create your account</p>
        <h1 id="page-heading">Join Vision Studio AI</h1>
        <p class="form-intro">Create an account to get started.</p>
        <?php if ($successMessage !== ''): ?>
            <?php
            $notificationType = 'success';
            $notificationMessage = $successMessage;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
            ?>
            <p class="form-footer">Didn’t receive the message? <a href="/verify-email">Request another verification link</a>.</p>
        <?php endif; ?>
        <?php if (isset($errors['form'])): ?>
            <p class="form-error" role="alert"><?= e($errors['form']) ?></p>
        <?php endif; ?>
        <form action="/register" method="post" class="auth-form" data-registration-form>
            <?= csrf_field() ?>
            <?php
            $fieldId = 'name';
            $fieldName = 'name';
            $fieldLabel = 'Full name';
            $fieldType = 'text';
            $fieldAutocomplete = 'name';
            $fieldMaxLength = 120;
            $fieldValue = (string) ($oldInput['name'] ?? '');
            $fieldInvalid = isset($errors['name']);
            $fieldDescribedBy = isset($errors['name']) ? 'name-error' : null;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'form-field.php';
            ?>
            <?php if (isset($errors['name'])): ?>
                <p class="field-error" id="name-error" role="alert"><?= e($errors['name']) ?></p>
            <?php endif; ?>
            <?php
            $fieldId = 'email';
            $fieldName = 'email';
            $fieldLabel = 'Email';
            $fieldType = 'email';
            $fieldAutocomplete = 'email';
            $fieldMaxLength = 254;
            $fieldValue = (string) ($oldInput['email'] ?? '');
            $fieldInvalid = isset($errors['email']);
            $fieldDescribedBy = isset($errors['email']) ? 'email-error' : null;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'form-field.php';
            ?>
            <?php if (isset($errors['email'])): ?>
                <p class="field-error" id="email-error" role="alert"><?= e($errors['email']) ?></p>
            <?php endif; ?>
            <label for="password">Password</label>
            <div class="password-input">
                <input id="password" name="password" type="password"
                    autocomplete="new-password" required
                    aria-describedby="password-requirements<?= isset($errors['password']) ? ' password-error' : '' ?>"
                    aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
                <button class="password-toggle" type="button" data-password-toggle="password"
                    aria-label="Show password" aria-controls="password" aria-pressed="false">Show</button>
            </div>
            <div class="password-guidance" id="password-requirements" aria-live="polite">
                <p>Password requirements</p>
                <ul>
                    <li data-password-rule="minimum" data-state="waiting">
                        <span>At least 7 characters</span>
                        <span class="rule-status" aria-live="polite"></span>
                    </li>
                </ul>
            </div>
            <?php if (isset($errors['password'])): ?>
                <p class="field-error" id="password-error" role="alert"><?= e($errors['password']) ?></p>
            <?php endif; ?>
            <label for="password_confirmation">Confirm password</label>
            <div class="password-input">
                <input id="password_confirmation" name="password_confirmation" type="password"
                    autocomplete="new-password" required
                    aria-describedby="<?= isset($errors['password_confirmation']) ? 'confirmation-error' : 'password-match' ?>"
                    aria-invalid="<?= isset($errors['password_confirmation']) ? 'true' : 'false' ?>">
                <button class="password-toggle" type="button" data-password-toggle="password_confirmation"
                    aria-label="Show confirmation password" aria-controls="password_confirmation"
                    aria-pressed="false">Show</button>
            </div>
            <p class="password-match" id="password-match" data-state="waiting" aria-live="polite">
                Enter a password to check the match.
            </p>
            <?php if (isset($errors['password_confirmation'])): ?>
                <p class="field-error" id="confirmation-error" role="alert"><?= e($errors['password_confirmation']) ?></p>
            <?php endif; ?>
            <div class="terms-field">
                <label for="terms_accepted">
                    <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1"
                        required aria-invalid="<?= isset($errors['terms']) ? 'true' : 'false' ?>"
                        <?= $termsAccepted ? 'checked' : '' ?>
                        <?= isset($errors['terms']) ? 'aria-describedby="terms-error"' : '' ?>>
                    <span>I accept the Terms and Conditions.</span>
                </label>
                <?php if (isset($errors['terms'])): ?>
                    <p class="field-error" id="terms-error" role="alert"><?= e($errors['terms']) ?></p>
                <?php endif; ?>
            </div>
            <?php
            $buttonLabel = 'Create account';
            $buttonClass = 'button button-primary form-submit';
            $buttonType = 'submit';
            $buttonDisabled = false;
            $buttonLoadingLabel = 'Creating your account...';
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'button.php';
            ?>
        </form>
        <p class="form-footer">Already registered? <a href="/login">Log in</a></p>
    </section>
</main>
