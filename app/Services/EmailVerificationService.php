<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\Environment;
use App\Models\User;
use PDO;
use RuntimeException;
use Throwable;

final class EmailVerificationService
{
    private const TOKEN_LIFETIME_HOURS = 24;
    private const MAX_REQUESTS = 5;

    public function __construct(private readonly ?PasswordResetMailer $mailer = null)
    {
    }

    public function sendForUser(int $userId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $findUser = $pdo->prepare(
                'SELECT `email`, `email_verified_at`
                 FROM `users`
                 WHERE `id` = :id AND `status` = :status
                 LIMIT 1
                 FOR UPDATE'
            );
            $findUser->execute([
                'id' => $userId,
                'status' => 'active',
            ]);
            $user = $findUser->fetch();

            if ($user === false || $user['email_verified_at'] !== null) {
                $pdo->commit();
                return;
            }

            $invalidateOldTokens = $pdo->prepare(
                'UPDATE `email_verification_tokens`
                 SET `used_at` = CURRENT_TIMESTAMP
                 WHERE `user_id` = :user_id AND `used_at` IS NULL'
            );
            $invalidateOldTokens->execute(['user_id' => $userId]);

            $token = bin2hex(random_bytes(32));
            $insert = $pdo->prepare(
                'INSERT INTO `email_verification_tokens` (`user_id`, `token_hash`, `expires_at`)
                 VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL '
                . self::TOKEN_LIFETIME_HOURS . ' HOUR))'
            );
            $insert->execute([
                'user_id' => $userId,
                'token_hash' => hash('sha256', $token),
            ]);
            $email = (string) $user['email'];
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        try {
            [$htmlBody, $textBody] = $this->renderEmail($this->verificationUrl($token));
            ($this->mailer ?? new SmtpMailer())->send(
                $email,
                'Verify your Vision Studio AI email address',
                $htmlBody,
                $textBody
            );
        } catch (RuntimeException $exception) {
            Logger::error($exception);
        }
    }

    public function request(string $email, string $ipAddress): void
    {
        $email = strtolower(trim($email));
        $pdo = Database::connection();
        $limiter = new AuthRateLimiter($pdo, self::MAX_REQUESTS);
        $buckets = $limiter->bucketsFor($email, $ipAddress, 'email_verification');

        if ($limiter->isLimited($buckets)) {
            throw new TooManyEmailVerificationRequests('Please wait before requesting another verification email.');
        }

        $limiter->recordFailure($buckets);

        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $user = User::findByEmail($email);

        if ($user === null || $user['status'] !== 'active' || $user['email_verified_at'] !== null) {
            return;
        }

        $this->sendForUser((int) $user['id']);
    }

    public function isValidToken(string $token): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM `email_verification_tokens` AS `tokens`
             INNER JOIN `users` ON `users`.`id` = `tokens`.`user_id`
             WHERE `tokens`.`token_hash` = :token_hash
               AND `tokens`.`used_at` IS NULL
               AND `tokens`.`expires_at` > CURRENT_TIMESTAMP
               AND `users`.`status` = :status
               AND `users`.`email_verified_at` IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function verify(string $token): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $findToken = $pdo->prepare(
                'SELECT `tokens`.`id`, `tokens`.`user_id`
                 FROM `email_verification_tokens` AS `tokens`
                 INNER JOIN `users` ON `users`.`id` = `tokens`.`user_id`
                 WHERE `tokens`.`token_hash` = :token_hash
                   AND `tokens`.`used_at` IS NULL
                   AND `tokens`.`expires_at` > CURRENT_TIMESTAMP
                   AND `users`.`status` = :status
                   AND `users`.`email_verified_at` IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $findToken->execute([
                'token_hash' => hash('sha256', $token),
                'status' => 'active',
            ]);
            $record = $findToken->fetch();

            if ($record === false) {
                $pdo->rollBack();
                return false;
            }

            $verifyUser = $pdo->prepare(
                'UPDATE `users`
                 SET `email_verified_at` = CURRENT_TIMESTAMP
                 WHERE `id` = :user_id AND `email_verified_at` IS NULL'
            );
            $verifyUser->execute(['user_id' => $record['user_id']]);

            if ($verifyUser->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }

            $useTokens = $pdo->prepare(
                'UPDATE `email_verification_tokens`
                 SET `used_at` = CURRENT_TIMESTAMP
                 WHERE `user_id` = :user_id AND `used_at` IS NULL'
            );
            $useTokens->execute(['user_id' => $record['user_id']]);
            (new UserActivityService())->record((int) $record['user_id'], 'email_verified');
            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function verificationUrl(string $token): string
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
            throw new RuntimeException('APP_URL must be an absolute HTTP or HTTPS base URL.');
        }

        return rtrim($baseUrl, '/') . '/verify-email?token=' . rawurlencode($token);
    }

    /**
     * @return array{string, string}
     */
    private function renderEmail(string $verificationUrl): array
    {
        $template = BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR
            . 'emails' . DIRECTORY_SEPARATOR . 'email-verification.php';

        if (!is_file($template)) {
            throw new RuntimeException('The email verification template is missing.');
        }

        ob_start();
        require $template;
        $htmlBody = ob_get_clean();

        if (!is_string($htmlBody)) {
            throw new RuntimeException('Unable to render the email verification message.');
        }

        $textBody = "Vision Studio AI email verification\n\n"
            . "Verify your email address using this secure link:\n{$verificationUrl}\n\n"
            . 'This link expires in 24 hours. If you did not create an account, you can ignore this email.';

        return [$htmlBody, $textBody];
    }
}
