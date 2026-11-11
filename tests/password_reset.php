<?php

declare(strict_types=1);

use App\Config\Database;
use App\Config\Environment;
use App\Models\User;
use App\Services\AuthRateLimiter;
use App\Services\AuthService;
use App\Services\PasswordResetMailer;
use App\Services\PasswordResetService;
use App\Services\TooManyPasswordResetRequests;
use App\Services\ValidationException;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

final class TestPasswordResetMailer implements PasswordResetMailer
{
    /**
     * @var list<array{recipient: string, subject: string, html: string, text: string}>
     */
    public array $messages = [];

    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        $this->messages[] = [
            'recipient' => $recipient,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $textBody,
        ];
    }
}

$pdo = Database::connection();
$passed = 0;
$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $passed++;
    fwrite(STDOUT, "PASS {$message}\n");
};

$email = 'password-reset-' . bin2hex(random_bytes(8)) . '@example.test';
$invalidEmail = 'invalid-reset-' . bin2hex(random_bytes(8));
$rateLimitEmail = 'limited-reset-' . bin2hex(random_bytes(8)) . '@example.test';
$ipAddress = '203.0.113.43';
$rateLimitIp = '203.0.113.44';
$oldAppUrl = getenv('APP_URL');
putenv('APP_URL=http://127.0.0.1:8000');
$mailer = new TestPasswordResetMailer();
$service = new PasswordResetService($mailer);
$testBuckets = [];

foreach ([
    [$email, $ipAddress],
    [$invalidEmail, $ipAddress],
    [$rateLimitEmail, $rateLimitIp],
] as [$bucketEmail, $bucketIp]) {
    array_push(
        $testBuckets,
        ...((new AuthRateLimiter($pdo))->bucketsFor($bucketEmail, $bucketIp, 'password_reset'))
    );
}
array_push(
    $testBuckets,
    ...(new AuthRateLimiter($pdo))->bucketsFor($email, $ipAddress),
    ...(new AuthRateLimiter($pdo))->bucketsFor($email, $ipAddress, 'login')
);

try {
    $user = User::create('Password Reset Test', $email, password_hash('OldPassword7', PASSWORD_DEFAULT), true);

    if ($user === null) {
        throw new RuntimeException('Unable to create the password reset test account.');
    }

    $service->request($email, $ipAddress);
    $assert(count($mailer->messages) === 1, 'valid reset request sends one email through the configured mail transport');
    $resetRequestActivity = $pdo->prepare(
        'SELECT `description`
         FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type
         ORDER BY `id` DESC LIMIT 1'
    );
    $resetRequestActivity->execute([
        'user_id' => $user['id'],
        'activity_type' => 'password_reset_requested',
    ]);
    $resetRequestDescription = $resetRequestActivity->fetchColumn();
    $message = $mailer->messages[0];
    preg_match('/https?:\/\/[^\s"]+\/reset-password\?token=([a-f0-9]{64})/', $message['text'], $tokenMatch);
    $token = $tokenMatch[1] ?? '';
    $assert(
        is_string($resetRequestDescription)
            && str_contains($resetRequestDescription, 'password reset link was requested')
            && !str_contains($resetRequestDescription, $token),
        'reset requests record safe account activity without storing reset tokens'
    );
    $assert(
        $message['recipient'] === $email
            && str_contains($message['subject'], 'Vision Studio AI')
            && str_contains($message['html'], 'Vision Studio AI')
            && str_contains($message['html'], 'expires in 30 minutes')
            && str_contains($message['html'], 'ignore this email'),
        'reset email has branding, expiry guidance, and an ignore notice'
    );
    $assert(preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1, 'reset email contains a cryptographically random token');

    $findToken = $pdo->prepare(
        'SELECT `token_hash`, `expires_at`, `used_at`
         FROM `password_reset_tokens` WHERE `user_id` = :user_id
         ORDER BY `id` DESC LIMIT 1'
    );
    $findToken->execute(['user_id' => $user['id']]);
    $tokenRecord = $findToken->fetch();
    $assert(
        is_array($tokenRecord)
            && hash_equals((string) $tokenRecord['token_hash'], hash('sha256', $token))
            && !hash_equals((string) $tokenRecord['token_hash'], $token),
        'only the SHA-256 token hash is stored in the database'
    );
    $assert(
        is_array($tokenRecord)
            && (int) $pdo->query(
                'SELECT TIMESTAMPDIFF(MINUTE, CURRENT_TIMESTAMP, '
                . $pdo->quote((string) $tokenRecord['expires_at']) . ')'
            )->fetchColumn() >= 29
            && $tokenRecord['used_at'] === null,
        'reset token is unused and expires after approximately 30 minutes'
    );
    $assert($service->isValidToken($token), 'a valid reset token is accepted');
    $assert(!$service->isValidToken('not-a-valid-token'), 'an invalid reset token is rejected');

    $expiredToken = bin2hex(random_bytes(32));
    $insertExpired = $pdo->prepare(
        'INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE))'
    );
    $insertExpired->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $expiredToken),
    ]);
    $assert(!$service->isValidToken($expiredToken), 'an expired reset token is rejected');

    try {
        $service->reset($token, 'NewPassword7', 'DifferentPassword7');
        throw new RuntimeException('Password confirmation mismatch was accepted.');
    } catch (ValidationException $exception) {
        $assert(
            isset($exception->errors()['password_confirmation']),
            'password mismatch is rejected without consuming the reset token'
        );
    }
    try {
        $service->reset($token, 'Short6', 'Short6');
        throw new RuntimeException('Weak replacement password was accepted.');
    } catch (ValidationException $exception) {
        $assert(
            isset($exception->errors()['password']),
            'weak replacement password is rejected without consuming the reset token'
        );
    }

    $service->request($email, $ipAddress);
    preg_match(
        '/https?:\/\/[^\s"]+\/reset-password\?token=([a-f0-9]{64})/',
        $mailer->messages[1]['text'],
        $nextTokenMatch
    );
    $nextToken = $nextTokenMatch[1] ?? '';
    $assert(
        preg_match('/\A[a-f0-9]{64}\z/D', $nextToken) === 1
            && $service->isValidToken($token)
            && $service->isValidToken($nextToken),
        'multiple reset links remain valid until a password reset succeeds'
    );

    $outstandingToken = bin2hex(random_bytes(32));
    $insertOutstanding = $pdo->prepare(
        'INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
    );
    $insertOutstanding->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $outstandingToken),
    ]);
    $rememberHash = hash('sha256', bin2hex(random_bytes(32)));
    $insertRemember = $pdo->prepare(
        'INSERT INTO `remember_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 DAY))'
    );
    $insertRemember->execute([
        'user_id' => $user['id'],
        'token_hash' => $rememberHash,
    ]);

    $assert(
        $service->reset($nextToken, 'NewPassword7', 'NewPassword7'),
        'valid reset atomically changes the password and consumes reset tokens'
    );
    $verifiedAt = $pdo->prepare('SELECT `email_verified_at` FROM `users` WHERE `id` = :user_id');
    $verifiedAt->execute(['user_id' => $user['id']]);
    $assert($verifiedAt->fetchColumn() !== false, 'a successful email-delivered password reset confirms the account email');
    $assert(
        !$service->isValidToken($token)
            && !$service->isValidToken($nextToken)
            && !$service->isValidToken($outstandingToken),
        'successful reset invalidates its token and all other outstanding reset tokens'
    );
    $rememberCount = $pdo->prepare('SELECT COUNT(*) FROM `remember_tokens` WHERE `user_id` = :user_id');
    $rememberCount->execute(['user_id' => $user['id']]);
    $assert((int) $rememberCount->fetchColumn() === 0, 'successful reset revokes persistent remembered logins');
    $resetActivityCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type'
    );
    $resetActivityCount->execute([
        'user_id' => $user['id'],
        'activity_type' => 'password_reset',
    ]);
    $assert((int) $resetActivityCount->fetchColumn() === 1, 'successful password resets create a security notification');

    $auth = new AuthService();
    $assert($auth->authenticate($email, 'OldPassword7', $ipAddress) === null, 'old password cannot log in after reset');
    $newLogin = $auth->authenticate($email, 'NewPassword7', $ipAddress);
    $assert(is_array($newLogin) && $newLogin['id'] === $user['id'], 'new password can log in after reset');
    $assert(
        !$service->reset($nextToken, 'AnotherPassword7', 'AnotherPassword7'),
        'a used reset token cannot be reused'
    );

    try {
        $service->request($invalidEmail, $ipAddress);
        throw new RuntimeException('Invalid email address was accepted.');
    } catch (ValidationException $exception) {
        $assert(isset($exception->errors()['email']), 'reset request rejects invalid email format');
    }

    $limitedService = new PasswordResetService(new TestPasswordResetMailer());
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $limitedService->request($rateLimitEmail, $rateLimitIp);
    }

    try {
        $limitedService->request($rateLimitEmail, $rateLimitIp);
        throw new RuntimeException('Password reset rate limit was not enforced.');
    } catch (TooManyPasswordResetRequests) {
        $assert(true, 'reset requests are rate limited by email and IP');
    }

    fwrite(STDOUT, sprintf("All %d password reset checks passed.\n", $passed));
} finally {
    $deleteUser = $pdo->prepare('DELETE FROM `users` WHERE `email` = :email');
    $deleteUser->execute(['email' => $email]);
    $deleteBuckets = $pdo->prepare('DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash');
    foreach (array_unique($testBuckets) as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }

    if ($oldAppUrl === false) {
        putenv('APP_URL');
    } else {
        putenv('APP_URL=' . $oldAppUrl);
    }
}
