<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\Environment;
use App\Models\User;
use RuntimeException;
use Throwable;

final class PasswordResetService
{
    private const TOKEN_LIFETIME_MINUTES = 30;
    private const MAX_REQUESTS = 5;

    private readonly PasswordResetMailer $mailer;

    public function __construct(?PasswordResetMailer $mailer = null)
    {
        $this->mailer = $mailer ?? new SmtpMailer();
    }

    public function request(string $email, string $ipAddress): void
    {
        $email = strtolower(trim($email));
        $pdo = Database::connection();
        $limiter = new AuthRateLimiter($pdo, self::MAX_REQUESTS);
        $buckets = $limiter->bucketsFor($email, $ipAddress, 'password_reset');

        if ($limiter->isLimited($buckets)) {
            throw new TooManyPasswordResetRequests('Please wait before requesting another password reset link.');
        }

        $limiter->recordFailure($buckets);

        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['email' => 'Enter a valid email address.']);
        }

        $user = User::findByEmail($email);

        if ($user === null || $user['status'] !== 'active') {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`)
                 VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL '
                . self::TOKEN_LIFETIME_MINUTES . ' MINUTE))'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'token_hash' => $tokenHash,
            ]);
            (new UserActivityService())->record((int) $user['id'], 'password_reset_requested');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        try {
            $resetUrl = $this->resetUrl($token);
            [$htmlBody, $textBody] = $this->renderEmail($resetUrl);
            $this->mailer->send(
                (string) $user['email'],
                'Reset your Vision Studio AI password',
                $htmlBody,
                $textBody
            );
        } catch (RuntimeException $exception) {
            Logger::error($exception);
        }
    }

    public function isValidToken(string $token): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM `password_reset_tokens` AS `tokens`
             INNER JOIN `users` ON `users`.`id` = `tokens`.`user_id`
             WHERE `tokens`.`token_hash` = :token_hash
               AND `tokens`.`used_at` IS NULL
               AND `tokens`.`expires_at` > CURRENT_TIMESTAMP
               AND `users`.`status` = :status
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function reset(string $token, string $password, string $confirmation): bool
    {
        $errors = [];
        $passwordCharacters = preg_match_all('/./us', $password);

        if ($password === '') {
            $errors['password'] = 'Enter a new password.';
        } elseif ($passwordCharacters === false || $passwordCharacters < 7 || strlen($password) > 72) {
            $errors['password'] = 'Use at least 7 characters and no more than 72 bytes.';
        }

        if ($confirmation === '') {
            $errors['password_confirmation'] = 'Confirm your new password.';
        } elseif (!hash_equals($password, $confirmation)) {
            $errors['password_confirmation'] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $find = $pdo->prepare(
                'SELECT `tokens`.`id`, `tokens`.`user_id`
                 FROM `password_reset_tokens` AS `tokens`
                 INNER JOIN `users` ON `users`.`id` = `tokens`.`user_id`
                 WHERE `tokens`.`token_hash` = :token_hash
                   AND `tokens`.`used_at` IS NULL
                   AND `tokens`.`expires_at` > CURRENT_TIMESTAMP
                   AND `users`.`status` = :status
                 LIMIT 1
                 FOR UPDATE'
            );
            $find->execute([
                'token_hash' => hash('sha256', $token),
                'status' => 'active',
            ]);
            $record = $find->fetch();

            if ($record === false) {
                $pdo->rollBack();
                return false;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $updatePassword = $pdo->prepare(
                'UPDATE `users`
                 SET `password` = :password,
                     `email_verified_at` = COALESCE(`email_verified_at`, CURRENT_TIMESTAMP),
                     `auth_version` = `auth_version` + 1
                 WHERE `id` = :user_id'
            );
            $updatePassword->execute([
                'password' => $passwordHash,
                'user_id' => $record['user_id'],
            ]);

            $invalidate = $pdo->prepare(
                'UPDATE `password_reset_tokens`
                 SET `used_at` = CURRENT_TIMESTAMP
                 WHERE `user_id` = :user_id AND `used_at` IS NULL'
            );
            $invalidate->execute(['user_id' => $record['user_id']]);

            $revokeRememberedLogins = $pdo->prepare(
                'DELETE FROM `remember_tokens` WHERE `user_id` = :user_id'
            );
            $revokeRememberedLogins->execute(['user_id' => $record['user_id']]);

            (new UserActivityService())->record((int) $record['user_id'], 'password_reset');
            $pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function resetUrl(string $token): string
    {
        $baseUrl = Environment::required('APP_URL');
        $parts = parse_url($baseUrl);

        if (!is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new \RuntimeException('APP_URL must be an absolute HTTP or HTTPS base URL.');
        }

        return rtrim($baseUrl, '/') . '/reset-password?token=' . rawurlencode($token);
    }

    /**
     * @return array{string, string}
     */
    private function renderEmail(string $resetUrl): array
    {
        $template = BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR
            . 'emails' . DIRECTORY_SEPARATOR . 'password-reset.php';

        if (!is_file($template)) {
            throw new \RuntimeException('The password reset email template is missing.');
        }

        ob_start();
        require $template;
        $htmlBody = ob_get_clean();

        if (!is_string($htmlBody)) {
            throw new \RuntimeException('Unable to render the password reset email.');
        }

        $textBody = "Vision Studio AI password reset\n\n"
            . "Use this secure link to choose a new password:\n{$resetUrl}\n\n"
            . 'This link expires in 30 minutes. If you did not request a reset, you can ignore this email.';

        return [$htmlBody, $textBody];
    }
}
