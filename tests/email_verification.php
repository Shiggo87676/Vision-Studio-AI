<?php

declare(strict_types=1);

use App\Config\Database;
use App\Config\Environment;
use App\Models\User;
use App\Services\AuthRateLimiter;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetMailer;
use App\Services\TooManyEmailVerificationRequests;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

final class TestEmailVerificationMailer implements PasswordResetMailer
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
$email = 'email-verification-' . bin2hex(random_bytes(8)) . '@example.test';
$rateLimitEmail = 'verification-limit-' . bin2hex(random_bytes(8)) . '@example.test';
$ipAddress = '203.0.113.61';
$rateLimitIp = '203.0.113.62';
$oldAppUrl = getenv('APP_URL');
$oldSmtpHost = getenv('SMTP_HOST');
putenv('APP_URL=http://127.0.0.1:8000');
$mailer = new TestEmailVerificationMailer();
$service = new EmailVerificationService($mailer);
$rateLimitBuckets = (new AuthRateLimiter($pdo))->bucketsFor($rateLimitEmail, $rateLimitIp, 'email_verification');
$passed = 0;
$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $passed++;
    fwrite(STDOUT, "PASS {$message}\n");
};

try {
    $user = User::create('Email Verification Test', $email, password_hash('OldPassword7', PASSWORD_DEFAULT), true);

    if ($user === null) {
        throw new RuntimeException('Unable to create the email verification test account.');
    }

    $assert((new AuthService())->authenticate($email, 'OldPassword7', $ipAddress) === null, 'unverified accounts cannot authenticate');
    $assert(User::findActiveById((int) $user['id']) === null, 'unverified accounts cannot enter authenticated middleware');

    $service->sendForUser((int) $user['id']);
    $service->sendForUser((int) $user['id']);
    $assert(count($mailer->messages) === 2, 'verification emails use the injected mail transport');
    preg_match('/https?:\/\/[^\s"]+\/verify-email\?token=([a-f0-9]{64})/', $mailer->messages[0]['text'], $firstMatches);
    preg_match('/https?:\/\/[^\s"]+\/verify-email\?token=([a-f0-9]{64})/', $mailer->messages[1]['text'], $lastMatches);
    $firstToken = $firstMatches[1] ?? '';
    $token = $lastMatches[1] ?? '';
    $assert(
        $mailer->messages[1]['recipient'] === $email
            && str_contains($mailer->messages[1]['subject'], 'Verify')
            && str_contains($mailer->messages[1]['html'], 'expires in 24 hours')
            && str_contains($mailer->messages[1]['text'], 'ignore this email'),
        'verification email includes branding, expiry, and an ignore notice'
    );
    $assert(
        preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1
            && $service->isValidToken($token)
            && !$service->isValidToken($firstToken),
        'verification tokens are random, hashed, expiring, and replaced on resend'
    );
    $tokenRecord = $pdo->prepare(
        'SELECT `token_hash`, `expires_at`, `used_at`
         FROM `email_verification_tokens` WHERE `user_id` = :user_id
         ORDER BY `id` DESC LIMIT 1'
    );
    $tokenRecord->execute(['user_id' => $user['id']]);
    $storedToken = $tokenRecord->fetch();
    $assert(
        is_array($storedToken)
            && hash_equals((string) $storedToken['token_hash'], hash('sha256', $token))
            && !hash_equals((string) $storedToken['token_hash'], $token)
            && $storedToken['used_at'] === null,
        'only the token hash is stored and it remains unused until confirmation'
    );
    $assert($service->verify('invalid-token') === false, 'malformed verification tokens are rejected');
    $assert($service->verify($token), 'valid verification token confirms the email address');
    $assert(!$service->isValidToken($token) && !$service->verify($token), 'verified tokens cannot be reused');

    $verifiedAt = $pdo->prepare('SELECT `email_verified_at` FROM `users` WHERE `id` = :id');
    $verifiedAt->execute(['id' => $user['id']]);
    $assert($verifiedAt->fetchColumn() !== false, 'successful confirmation timestamps the verified email');
    $assert(is_array(User::findActiveById((int) $user['id'])), 'verified accounts become eligible for authenticated middleware');
    $login = (new AuthService())->authenticate($email, 'OldPassword7', $ipAddress);
    $assert(is_array($login) && $login['id'] === $user['id'], 'verified users can authenticate normally');

    $expiredToken = bin2hex(random_bytes(32));
    $insertExpired = $pdo->prepare(
        'INSERT INTO `email_verification_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 MINUTE))'
    );
    $insertExpired->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $expiredToken),
    ]);
    $assert(!$service->isValidToken($expiredToken) && !$service->verify($expiredToken), 'expired verification tokens are rejected');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $service->request($rateLimitEmail, $rateLimitIp);
    }
    try {
        $service->request($rateLimitEmail, $rateLimitIp);
        throw new RuntimeException('Email verification rate limiting was not enforced.');
    } catch (TooManyEmailVerificationRequests) {
        $assert(true, 'verification resend requests are rate limited by email and IP');
    }

    fwrite(STDOUT, sprintf("All %d email verification checks passed.\n", $passed));
} finally {
    if (isset($user['id'])) {
        $deleteUser = $pdo->prepare('DELETE FROM `users` WHERE `id` = :id');
        $deleteUser->execute(['id' => $user['id']]);
    }

    $deleteBuckets = $pdo->prepare(
        'DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash'
    );
    foreach ($rateLimitBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ((new AuthRateLimiter($pdo))->bucketsFor($email, $ipAddress) as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }

    if ($oldAppUrl === false) {
        putenv('APP_URL');
    } else {
        putenv('APP_URL=' . $oldAppUrl);
    }

    if ($oldSmtpHost === false) {
        putenv('SMTP_HOST');
    } else {
        putenv('SMTP_HOST=' . $oldSmtpHost);
    }
}
