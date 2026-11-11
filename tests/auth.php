<?php

declare(strict_types=1);

use App\Config\Database;
use App\Config\Session;
use App\Services\AuthRateLimiter;
use App\Services\UserActivityService;
use App\Models\User;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$pdo = Database::connection();
$migration = $pdo->prepare(
    'SELECT COUNT(*) FROM `migrations`
     WHERE `migration` IN (:rate_limit_migration, :remember_migration, :terms_migration, :activity_migration, :verification_migration)'
);
$migration->execute([
    'rate_limit_migration' => '20261110000000_create_auth_login_rate_limits_table.php',
    'remember_migration' => '20261110000001_create_remember_tokens_table.php',
    'terms_migration' => '20261110000002_add_terms_accepted_at_to_users.php',
    'activity_migration' => '20261110000004_create_user_activities_table.php',
    'verification_migration' => '20261110000005_create_email_verification_tokens_table.php',
]);

if ((int) $migration->fetchColumn() !== 5
    || !in_array('auth_version', $pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_COLUMN), true)) {
    throw new RuntimeException('Run php database/migrate.php before authentication tests.');
}

$passed = 0;
$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $passed++;
    fwrite(STDOUT, "PASS {$message}\n");
};

$cascadeEmail = 'activity-cascade-' . bin2hex(random_bytes(6)) . '@example.test';
$cascadeUser = User::create(
    'Activity Cascade Test',
    $cascadeEmail,
    password_hash('ActivityTest7', PASSWORD_DEFAULT),
    true
);

if ($cascadeUser === null) {
    throw new RuntimeException('Unable to create the user-activity cascade test account.');
}

(new UserActivityService())->record((int) $cascadeUser['id'], 'account_registered');
$deleteCascadeUser = $pdo->prepare('DELETE FROM `users` WHERE `id` = :id');
$deleteCascadeUser->execute(['id' => $cascadeUser['id']]);
$cascadeCount = $pdo->prepare('SELECT COUNT(*) FROM `user_activities` WHERE `user_id` = :user_id');
$cascadeCount->execute(['user_id' => $cascadeUser['id']]);
$assert((int) $cascadeCount->fetchColumn() === 0, 'user activity records cascade on account deletion');

$temporaryEmail = 'phase2-' . bin2hex(random_bytes(8)) . '@example.test';
$temporaryName = 'Phase Two Test';
$temporaryPassword = 'Seven77';
$disabledEmail = 'disabled-' . bin2hex(random_bytes(8)) . '@example.test';
$unknownLoginEmail = 'unknown-' . bin2hex(random_bytes(5)) . '@example.test';
$invalidRegistrationEmail = 'not-an-email-' . bin2hex(random_bytes(5));
$resetInvalidEmail = 'not-a-reset-email-' . bin2hex(random_bytes(5));
$resetUnknownEmail = 'unknown-reset-' . bin2hex(random_bytes(6)) . '@example.test';
$unknownVerificationEmail = 'unknown-verification-' . bin2hex(random_bytes(5)) . '@example.test';
$testServerHost = '127.0.0.2';
$testServerIp = '203.0.113.47';
$limiter = new AuthRateLimiter($pdo);
$testBuckets = $limiter->bucketsFor($temporaryEmail, $testServerIp);
$registrationTestBuckets = array_merge(
    $limiter->bucketsFor($temporaryEmail, $testServerIp, 'registration'),
    $limiter->bucketsFor($invalidRegistrationEmail, $testServerIp, 'registration'),
    $limiter->bucketsFor('missing-email-' . hash('sha256', $testServerIp), $testServerIp, 'registration')
);
$passwordChangeTestBuckets = $limiter->bucketsFor($temporaryEmail, $testServerIp, 'password_change');
$passwordResetTestBuckets = array_merge(
    $limiter->bucketsFor($resetInvalidEmail, $testServerIp, 'password_reset'),
    $limiter->bucketsFor($resetUnknownEmail, $testServerIp, 'password_reset')
);
$verificationRequestBuckets = array_merge(
    $limiter->bucketsFor($temporaryEmail, $testServerIp, 'email_verification'),
    $limiter->bucketsFor($unknownVerificationEmail, $testServerIp, 'email_verification')
);
$additionalLoginTestBuckets = array_merge(
    $limiter->bucketsFor($disabledEmail, $testServerIp),
    $limiter->bucketsFor($unknownLoginEmail, $testServerIp),
    $limiter->bucketsFor('missing-email-' . hash('sha256', $testServerIp), $testServerIp)
);
$rememberTokenHashes = [];
$portSocket = stream_socket_server('tcp://' . $testServerHost . ':0', $errorCode, $errorMessage);

if ($portSocket === false) {
    throw new RuntimeException("Unable to allocate a local test port: {$errorMessage}");
}

$socketName = stream_socket_get_name($portSocket, false);
fclose($portSocket);

if (!is_string($socketName) || !preg_match('/:(\d+)$/', $socketName, $portMatch)) {
    throw new RuntimeException('Unable to determine the local authentication test port.');
}

$port = (int) $portMatch[1];
$serverLog = tempnam(sys_get_temp_dir(), 'vsai-auth-server-');
$sessionIds = [];

if ($serverLog === false) {
    throw new RuntimeException('Unable to create the authentication test server log.');
}

$pipes = [];
$server = proc_open(
    [
        PHP_BINARY,
        '-S',
        "{$testServerHost}:{$port}",
        '-t',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'router.php',
    ],
    [
        0 => ['pipe', 'r'],
        1 => ['file', $serverLog, 'a'],
        2 => ['file', $serverLog, 'a'],
    ],
    $pipes,
    dirname(__DIR__)
);

if (!is_resource($server)) {
    unlink($serverLog);
    throw new RuntimeException('Unable to start the local authentication test server.');
}

fclose($pipes[0]);

$serverReady = false;
for ($attempt = 0; $attempt < 50; $attempt++) {
    $connection = @fsockopen($testServerHost, $port, $errorCode, $errorMessage, 0.1);

    if (is_resource($connection)) {
        fclose($connection);
        $serverReady = true;
        break;
    }

    usleep(100000);
}

if (!$serverReady) {
    proc_terminate($server);
    proc_close($server);
    $logContents = file_get_contents($serverLog);
    @unlink($serverLog);
    throw new RuntimeException('The local authentication test server did not start: ' . $logContents);
}

$request = static function (string $method, string $path, array $data = [], string $cookie = '') use ($port, $testServerHost, &$sessionIds, &$rememberTokenHashes): array {
    $headers = ['Accept: text/html'];

    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }

    $options = [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 10,
    ];

    if ($method === 'POST') {
        $options['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
        $options['content'] = http_build_query($data);
    }

    $context = stream_context_create(['http' => $options]);
    $body = file_get_contents("http://{$testServerHost}:{$port}{$path}", false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    $cookieJar = [];

    foreach (explode(';', $cookie) as $cookiePair) {
        $parts = explode('=', trim($cookiePair), 2);

        if (count($parts) === 2) {
            $cookieJar[$parts[0]] = $parts[1];
        }
    }

    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $statusMatch)) {
            $status = (int) $statusMatch[1];
        }

        if (preg_match('/^Set-Cookie:\s*([A-Za-z0-9_]+)=([^;]*);/i', $header, $cookieMatch)) {
            $cookieName = $cookieMatch[1];
            $cookieValue = $cookieMatch[2];
            $isExpired = preg_match('/;\s*expires=([^;]+)/i', $header, $expiryMatch) === 1
                && strtotime($expiryMatch[1]) <= time();

            if ($cookieValue === '' || strtolower($cookieValue) === 'deleted' || $isExpired) {
                unset($cookieJar[$cookieName]);
            } else {
                $cookieJar[$cookieName] = $cookieValue;
            }

            if ($cookieName === 'VSAI_SESSION' && $cookieValue !== '') {
                $sessionIds[] = $cookieValue;
            }

            if ($cookieName === 'VSAI_REMEMBER' && $cookieValue !== '') {
                $rememberTokenHashes[] = hash('sha256', $cookieValue);
            }
        }
    }

    if ($body === false || $status === 0) {
        throw new RuntimeException("No valid HTTP response from {$method} {$path}.");
    }

    $nextCookie = [];

    foreach ($cookieJar as $name => $value) {
        $nextCookie[] = $name . '=' . $value;
    }

    return [$status, $body, implode('; ', $nextCookie), $headers];
};

$csrfToken = static function (string $body): string {
    if (!preg_match('/name="_csrf_token" value="([a-f0-9]{64})"/', $body, $matches)) {
        throw new RuntimeException('The page did not contain a valid CSRF token.');
    }

    return $matches[1];
};

$get = static function (string $path, string &$cookie) use ($request): array {
    [$status, $body, $cookie, $headers] = $request('GET', $path, [], $cookie);

    return [$status, $body, $headers];
};

$post = static function (string $path, array $data, string &$cookie) use ($request): array {
    [$status, $body, $cookie, $headers] = $request('POST', $path, $data, $cookie);

    return [$status, $body, $headers];
};

$cookie = '';

try {
    [$status, , $headers] = $get('/account', $cookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'protected account redirects unauthenticated visitors');
    $assert(
        str_contains($cookie, 'VSAI_SESSION=')
            && stripos(implode("\n", $headers), 'HttpOnly') !== false
            && stripos(implode("\n", $headers), 'SameSite=Lax') !== false,
        'session cookie is HTTP-only and same-site'
    );
    [$status, , $headers] = $get('/dashboard', $cookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'direct dashboard access redirects unauthenticated visitors');
    [$status, , $headers] = $get('/settings', $cookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'settings redirects unauthenticated visitors');
    [$status, , $headers] = $get('/verify-email', $cookie);
    $assert($status === 200 && in_array('Referrer-Policy: no-referrer', $headers, true), 'email verification request page is public and suppresses referrers');
    [$status, , $headers] = $post('/verify-email', ['token' => 'invalid', '_csrf_token' => 'invalid'], $cookie);
    $assert($status === 419, 'email verification rejects invalid CSRF tokens');
    [$status, , $headers] = $get('/verify-email/resend', $cookie);
    $assert($status === 404, 'verification resend endpoint does not accept GET requests');
    [$status, , $headers] = $post('/notifications/read', ['_csrf_token' => 'invalid'], $cookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'notification read endpoint redirects unauthenticated visitors');

    $idleExpiryCookie = '';
    [$status] = $get('/login', $idleExpiryCookie);
    preg_match('/\AVSAI_SESSION=([A-Za-z0-9,-]+)\z/D', $idleExpiryCookie, $idleSessionMatch);
    $idleSessionFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'sessions' . DIRECTORY_SEPARATOR . 'sess_' . ($idleSessionMatch[1] ?? '');
    file_put_contents(
        $idleSessionFile,
        'user_id|i:999999;created_at|i:' . time() . ';last_activity|i:'
            . (time() - Session::IDLE_TIMEOUT_SECONDS - 1) . ';'
    );
    $idleExpiredSessionId = $idleSessionMatch[1] ?? '';
    [$status, , $headers] = $get('/dashboard', $idleExpiryCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true)
            && !str_contains($idleExpiryCookie, $idleExpiredSessionId),
        'idle-expired sessions are invalidated before protected access'
    );

    $absoluteExpiryCookie = '';
    [$status] = $get('/login', $absoluteExpiryCookie);
    preg_match('/\AVSAI_SESSION=([A-Za-z0-9,-]+)\z/D', $absoluteExpiryCookie, $absoluteSessionMatch);
    $absoluteSessionFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
        . 'sessions' . DIRECTORY_SEPARATOR . 'sess_' . ($absoluteSessionMatch[1] ?? '');
    file_put_contents(
        $absoluteSessionFile,
        'user_id|i:999999;created_at|i:' . (time() - Session::ABSOLUTE_TIMEOUT_SECONDS - 1)
            . ';last_activity|i:' . time() . ';'
    );
    $absoluteExpiredSessionId = $absoluteSessionMatch[1] ?? '';
    [$status, , $headers] = $get('/dashboard', $absoluteExpiryCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true)
            && !str_contains($absoluteExpiryCookie, $absoluteExpiredSessionId),
        'absolute-expired sessions are invalidated before protected access'
    );

    [$status, $body, $headers] = $get('/register', $cookie);
    $registerToken = $csrfToken($body);
    $assert($status === 200 && str_contains($body, 'Create an account'), 'registration form loads with a CSRF token');
    $assert(
        !str_contains($body, 'No more than 72 UTF-8 bytes')
            && !str_contains($body, 'Not checked'),
        'registration page hides the byte-limit rule and unchecked status'
    );
    $assert(
        str_contains($body, 'id="password-match"')
            && str_contains($body, 'Enter a password to check the match.')
            && !str_contains($body, '>Met</span>'),
        'registration restores the match indicator without a met requirement label'
    );
    $assert(
        str_contains($body, 'data-password-toggle="password"')
            && str_contains($body, 'data-password-toggle="password_confirmation"'),
        'registration provides independent show/hide controls for both password fields'
    );
    $assert(
        str_contains($body, 'id="terms_accepted"')
            && str_contains($body, 'I accept the Terms and Conditions.')
            && str_contains($body, 'data-loading-label="Creating your account..."'),
        'registration requires terms acceptance and provides a submit loading state'
    );
    [$status, $body] = $get('/forgot-password', $cookie);
    $assert(
        $status === 200
            && str_contains($body, 'Forgot your password?')
            && str_contains($body, 'action="/forgot-password"')
            && str_contains($body, 'name="_csrf_token"')
            && str_contains($body, 'Send reset link')
            && str_contains($body, 'href="/login"'),
        'forgot-password page provides an active CSRF-protected recovery form'
    );
    [$status] = $post('/forgot-password', [
        'email' => $temporaryEmail,
        '_csrf_token' => 'invalid',
    ], $cookie);
    $assert($status === 419, 'password reset request rejects an invalid CSRF token');
    [$status, $body] = $post('/forgot-password', [
        'email' => $resetInvalidEmail,
        '_csrf_token' => $csrfToken($body),
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'Enter a valid email address.'), 'password reset request validates email format');
    [$status, $body, $headers] = $post('/forgot-password', [
        'email' => $resetUnknownEmail,
        '_csrf_token' => $csrfToken($body),
    ], $cookie);
    $assert(
        $status === 303 && in_array('Location: /forgot-password', $headers, true),
        'reset requests use the same redirect for an unknown account'
    );
    [$status, $body] = $get('/forgot-password', $cookie);
    $assert(
        $status === 200 && str_contains($body, 'If an account matches that email address'),
        'reset request response does not reveal whether the account exists'
    );

    [$status, $body] = $get('/reset-password', $cookie);
    $assert(
        $status === 200
            && str_contains($body, 'Choose a new password')
            && str_contains($body, 'This reset link is invalid, expired, or has already been used')
            && !str_contains($body, 'name="password"'),
        'reset-password page requires a valid token before displaying the form'
    );
    [$status] = $post('/reset-password', [
        'token' => bin2hex(random_bytes(32)),
        'password' => 'NewPassword7',
        'password_confirmation' => 'NewPassword7',
        '_csrf_token' => 'invalid',
    ], $cookie);
    $assert($status === 419, 'password reset rejects an invalid CSRF token');

    [$status] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        '_csrf_token' => 'invalid',
    ], $cookie);
    $assert($status === 419, 'registration rejects an invalid CSRF token');

    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => 'Short6',
        'password_confirmation' => 'Short6',
        'terms_accepted' => '1',
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'at least 7 characters'), 'registration rejects passwords shorter than seven characters');

    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => 'Seven77',
        'password_confirmation' => 'Different7',
        'terms_accepted' => '1',
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'password confirmation does not match'), 'registration rejects mismatched passwords');

    $longPassword = str_repeat('a', 73);
    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $longPassword,
        'password_confirmation' => $longPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'no more than 72 bytes'), 'registration rejects passwords longer than 72 bytes');

    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $invalidRegistrationEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'valid email address'), 'registration rejects an invalid email address');

    [$status, $body] = $post('/register', [
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert(
        $status === 422
            && str_contains($body, 'Enter your full name')
            && str_contains($body, 'Enter your email address')
            && str_contains($body, 'Enter a password')
            && str_contains($body, 'Confirm your password')
            && str_contains($body, 'Accept the Terms and Conditions'),
        'registration reports missing required fields'
    );

    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'Accept the Terms and Conditions'), 'registration requires acceptance of terms');

    [$status, $body, $headers] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $registerToken,
    ], $cookie);
    $assert($status === 303 && in_array('Location: /register', $headers, true), 'valid registration redirects to its success page');

    [$status, $body] = $get('/register', $cookie);
    $assert(
        $status === 200
            && str_contains($body, 'Your account has been created.')
            && str_contains($body, 'Request another verification link'),
        'registration prompts the user to verify their email before signing in'
    );

    [$status, , $headers] = $get('/account', $cookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true),
        'successful registration does not automatically sign the user in'
    );

    $statement = $pdo->prepare(
        'SELECT `id`, `name`, `email`, `password`, `status`, `email_verified_at`, `terms_accepted_at`
         FROM `users` WHERE `email` = :email'
    );
    $statement->execute(['email' => $temporaryEmail]);
    $user = $statement->fetch();
    $userCount = $pdo->prepare('SELECT COUNT(*) FROM `users` WHERE `email` = :email');
    $userCount->execute(['email' => $temporaryEmail]);
    $assert(
        is_array($user)
            && password_verify($temporaryPassword, (string) $user['password'])
            && !hash_equals($temporaryPassword, (string) $user['password']),
        'registration stores only a password hash'
    );
    $assert(is_array($user) && $user['status'] === 'active', 'registration uses the existing active account status');
    $assert(is_array($user) && $user['email_verified_at'] === null, 'registration creates accounts in an unverified state');
    $assert(
        is_array($user) && $user['terms_accepted_at'] !== null,
        'registration records the terms acceptance timestamp'
    );
    $assert((int) $userCount->fetchColumn() === 1, 'successful registration creates exactly one user record');
    $registrationActivityCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type AND `is_notification` = 1'
    );
    $registrationActivityCount->execute([
        'user_id' => $user['id'],
        'activity_type' => 'account_registered',
    ]);
    $assert((int) $registrationActivityCount->fetchColumn() === 1, 'successful registration records one private activity and notification');

    [, $loginBeforeVerifyBody] = $get('/login', $cookie);
    $verifyUnverifiedLoginToken = $csrfToken($loginBeforeVerifyBody);
    [$status, $body] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => $verifyUnverifiedLoginToken,
    ], $cookie);
    $assert(
        $status === 422 && str_contains($body, 'The email or password is incorrect.'),
        'login denies unverified accounts with a generic credential message'
    );

    $verificationToken = bin2hex(random_bytes(32));
    $insertVerificationToken = $pdo->prepare(
        'INSERT INTO `email_verification_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 24 HOUR))'
    );
    $insertVerificationToken->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $verificationToken),
    ]);
    [$status, $verifyBody, $verifyHeaders] = $get('/verify-email?token=' . $verificationToken, $cookie);
    $assert(
        $status === 200
            && str_contains($verifyBody, 'Confirm your email')
            && str_contains($verifyBody, 'action="/verify-email"')
            && in_array('Referrer-Policy: no-referrer', $verifyHeaders, true),
        'valid verification link requires a CSRF-protected confirmation without referrer leakage'
    );
    [$status] = $post('/verify-email', [
        'token' => $verificationToken,
        '_csrf_token' => 'invalid',
    ], $cookie);
    $assert($status === 419, 'email verification rejects an invalid CSRF token');
    [$status, , $verifyHeaders] = $post('/verify-email', [
        'token' => $verificationToken,
        '_csrf_token' => $csrfToken($verifyBody),
    ], $cookie);
    $assert(
        $status === 303 && in_array('Location: /login', $verifyHeaders, true),
        'valid email verification activates the account and redirects to login'
    );
    $verifiedAt = $pdo->prepare('SELECT `email_verified_at` FROM `users` WHERE `id` = :id');
    $verifiedAt->execute(['id' => $user['id']]);
    $assert($verifiedAt->fetchColumn() !== false, 'email verification records the confirmation timestamp');
    $assert(
        !(new App\Services\EmailVerificationService())->verify($verificationToken),
        'email verification tokens are single use'
    );
    $verificationActivityCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type'
    );
    $verificationActivityCount->execute([
        'user_id' => $user['id'],
        'activity_type' => 'email_verified',
    ]);
    $assert((int) $verificationActivityCount->fetchColumn() === 1, 'email verification records account activity');

    [$status, , $verifyHeaders] = $post('/verify-email/resend', [
        'email' => $temporaryEmail,
        '_csrf_token' => $csrfToken($verifyBody),
    ], $cookie);
    $assert($status === 303 && in_array('Location: /verify-email', $verifyHeaders, true), 'verification resend returns the common response for a verified account');
    [, $verifiedResendBody] = $get('/verify-email', $cookie);
    [$status, , $verifyHeaders] = $post('/verify-email/resend', [
        'email' => $unknownVerificationEmail,
        '_csrf_token' => $csrfToken($verifiedResendBody),
    ], $cookie);
    $assert($status === 303 && in_array('Location: /verify-email', $verifyHeaders, true), 'verification resend returns the common response for an unknown account');
    [, $unknownResendBody] = $get('/verify-email', $cookie);
    $assert(
        str_contains($verifiedResendBody, 'If that address belongs to an unverified account')
            && str_contains($unknownResendBody, 'If that address belongs to an unverified account'),
        'verification resend does not reveal whether an account exists'
    );

    [$status, $body] = $get('/register', $cookie);
    $duplicateToken = $csrfToken($body);
    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $duplicateToken,
    ], $cookie);
    $assert(
        $status === 422
            && str_contains($body, 'We could not create your account with those details')
            && !str_contains($body, 'already exists'),
        'registration rejects duplicate email without revealing account existence'
    );
    $userCount->execute(['email' => $temporaryEmail]);
    $assert((int) $userCount->fetchColumn() === 1, 'duplicate registration does not create an additional user record');

    $failureTrigger = 'vsai_registration_failure_' . bin2hex(random_bytes(5));
    $pdo->exec(
        'CREATE TRIGGER `' . $failureTrigger . '`
         BEFORE INSERT ON `users`
         FOR EACH ROW
         SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'registration test database failure\''
    );

    try {
        [$status, $body] = $post('/register', [
            'name' => $temporaryName,
            'email' => 'failure-' . bin2hex(random_bytes(5)) . '@example.test',
            'password' => $temporaryPassword,
            'password_confirmation' => $temporaryPassword,
            'terms_accepted' => '1',
            '_csrf_token' => $registerToken,
        ], $cookie);
        $assert(
            $status === 500
                && str_contains($body, 'An unexpected error occurred')
                && !str_contains($body, 'registration test database failure')
                && !str_contains($body, 'SQLSTATE'),
            'database errors fail safely without exposing internals'
        );
    } finally {
        $pdo->exec('DROP TRIGGER IF EXISTS `' . $failureTrigger . '`');
    }

    [$status, $body] = $get('/login', $cookie);
    $loginToken = $csrfToken($body);
    $assert(
        str_contains($body, 'data-password-toggle="password"')
            && str_contains($body, 'aria-label="Show password"'),
        'login form provides an accessible password visibility control'
    );
    $assert(
        str_contains($body, 'id="remember_me"')
            && str_contains($body, 'Remember me for 90 days'),
        'login form offers the 90-day remember-me option'
    );

    [$status] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => 'invalid',
    ], $cookie);
    $assert($status === 419, 'login rejects an invalid CSRF token');

    [$status, $body] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => 'Incorrect-Password',
        'remember_me' => '1',
        '_csrf_token' => $loginToken,
    ], $cookie);
    $assert($status === 422 && str_contains($body, 'email or password is incorrect'), 'login rejects invalid credentials generically');
    $assert(str_contains($body, 'id="remember_me"') && str_contains($body, 'checked'), 'remember-me selection remains checked after an invalid login');

    [$status, $body] = $post('/login', [
        'email' => $unknownLoginEmail,
        'password' => 'Incorrect-Password',
        '_csrf_token' => $loginToken,
    ], $cookie);
    $assert(
        $status === 422
            && str_contains($body, 'The email or password is incorrect.'),
        'login gives the same generic response for an unknown email'
    );
    [$status, $body] = $post('/login', [
        '_csrf_token' => $loginToken,
    ], $cookie);
    $assert(
        $status === 422 && str_contains($body, 'The email or password is incorrect.'),
        "login handles missing credentials with the generic error (HTTP {$status})"
    );

    $createDisabledUser = $pdo->prepare(
        'INSERT INTO `users` (`name`, `email`, `password`, `status`, `terms_accepted_at`)
         VALUES (:name, :email, :password, :status, CURRENT_TIMESTAMP)'
    );
    $createDisabledUser->execute([
        'name' => 'Disabled Test User',
        'email' => $disabledEmail,
        'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
        'status' => 'disabled',
    ]);
    $findDisabledUser = $pdo->prepare('SELECT `id` FROM `users` WHERE `email` = :email');
    $findDisabledUser->execute(['email' => $disabledEmail]);
    $otherActivityUserId = (int) $findDisabledUser->fetchColumn();
    $insertOtherActivity = $pdo->prepare(
        'INSERT INTO `user_activities`
            (`user_id`, `activity_type`, `description`, `is_notification`)
         VALUES (:user_id, :activity_type, :description, 1)'
    );
    $insertOtherActivity->execute([
        'user_id' => $otherActivityUserId,
        'activity_type' => 'profile_updated',
        'description' => 'Private event belonging to another account.',
    ]);
    [$status, $body] = $post('/login', [
        'email' => $disabledEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => $loginToken,
    ], $cookie);
    $assert(
        $status === 422 && str_contains($body, 'email or password is incorrect'),
        'disabled accounts receive the generic invalid-credentials response'
    );

    $clearAttemptBucket = $pdo->prepare(
        'DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash'
    );
    $clearAttemptBucket->execute(['bucket_hash' => hash('sha256', 'ip:' . $testServerIp)]);

    $preLoginCookie = $cookie;
    [$status, , $headers] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => $loginToken,
    ], $cookie);
    $assert($status === 303 && in_array('Location: /dashboard', $headers, true), 'login accepts valid credentials and redirects to the dashboard');
    $assert($cookie !== $preLoginCookie, 'login regenerates the session identifier');
    $assert(!str_contains($cookie, 'VSAI_REMEMBER='), 'unchecked remember-me does not issue a persistent cookie');

    [$status, $body] = $get('/dashboard', $cookie);
    $assert(
        $status === 200
            && str_contains($body, $temporaryEmail)
            && str_contains($body, $temporaryName)
            && str_contains($body, 'Welcome back, Phase')
            && str_contains($body, 'Account status')
            && str_contains($body, 'Member since'),
        'dashboard shows the authenticated user and real account details'
    );
    $assert(
        str_contains($body, 'href="/dashboard#ai-studio"')
            && str_contains($body, 'href="/dashboard#projects"')
            && str_contains($body, 'href="/dashboard#activity"')
            && str_contains($body, 'href="/profile"')
            && str_contains($body, 'href="/settings"')
            && str_contains($body, 'action="/logout"'),
        'dashboard navigation links to available routes and workspace sections'
    );
    $assert(
        str_contains($body, 'No projects yet')
            && str_contains($body, 'Your Vision Studio AI account was created.')
            && str_contains($body, 'Project tracking isn’t enabled yet')
            && str_contains($body, 'Recorded account events'),
        'dashboard displays recorded activity count and an honest project empty state'
    );
    $assert(
        !str_contains($body, 'Private event belonging to another account.')
            && str_contains($body, 'Notifications')
            && str_contains($body, 'Mark all as read'),
        'dashboard notifications and activity are limited to the authenticated user'
    );
    [$status, $settingsBody] = $get('/settings', $cookie);
    $assert(
        $status === 200
            && str_contains($settingsBody, 'Account information')
            && str_contains($settingsBody, $temporaryEmail)
            && str_contains($settingsBody, 'Profile settings')
            && str_contains($settingsBody, 'Security settings')
            && str_contains($settingsBody, 'Account preferences')
            && str_contains($settingsBody, 'No configurable preferences are available yet.')
            && str_contains($settingsBody, 'href="/settings"')
            && str_contains($settingsBody, 'class="dashboard-nav-link active"')
            && str_contains($settingsBody, 'href="/profile"')
            && str_contains($settingsBody, 'href="/change-password"'),
        'settings displays actual account information and working profile and security controls'
    );
    $notificationCsrf = $csrfToken($body);
    [$status] = $post('/notifications/read', ['_csrf_token' => 'invalid'], $cookie);
    $assert($status === 419, 'marking notifications read rejects an invalid CSRF token');
    [$status, , $headers] = $post('/notifications/read', ['_csrf_token' => $notificationCsrf], $cookie);
    $assert(
        $status === 303 && in_array('Location: /dashboard', $headers, true),
        'authenticated users can mark their notifications as read'
    );
    $ownUnreadCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `is_notification` = 1 AND `read_at` IS NULL'
    );
    $ownUnreadCount->execute(['user_id' => $user['id']]);
    $otherUnreadCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `is_notification` = 1 AND `read_at` IS NULL'
    );
    $otherUnreadCount->execute(['user_id' => $otherActivityUserId]);
    $assert(
        (int) $ownUnreadCount->fetchColumn() === 0 && (int) $otherUnreadCount->fetchColumn() === 1,
        'marking notifications read changes only the signed-in user records'
    );
    [$status, , $headers] = $get('/login', $cookie);
    $assert($status === 303 && in_array('Location: /dashboard', $headers, true), 'authenticated users are redirected away from login');
    [$status, , $headers] = $get('/register', $cookie);
    $assert($status === 303 && in_array('Location: /dashboard', $headers, true), 'authenticated users are redirected away from registration');
    $loginAccountToken = $csrfToken($body);
    [$status] = $post('/logout', ['_csrf_token' => 'invalid'], $cookie);
    $assert($status === 419, 'logout rejects an invalid CSRF token');
    [, , $headers] = $post('/logout', ['_csrf_token' => $loginAccountToken], $cookie);
    $assert(in_array('Location: /', $headers, true), 'test account can log out after login');
    $logoutCookieExpired = false;
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: VSAI_SESSION=') === 0
            && preg_match('/;\s*expires=([^;]+)/i', $header, $expiry) === 1
            && strtotime($expiry[1]) < time()) {
            $logoutCookieExpired = true;
        }
    }
    $assert(
        $logoutCookieExpired && !str_contains($cookie, 'VSAI_SESSION='),
        'logout expires the authenticated session cookie'
    );
    [$status, $body] = $get('/', $cookie);
    $assert(
        $status === 200 && str_contains($body, 'You have been logged out successfully.'),
        'logout confirmation is displayed once on the homepage'
    );
    [$status, $body] = $get('/', $cookie);
    $assert(
        $status === 200 && !str_contains($body, 'You have been logged out successfully.'),
        'logout confirmation is cleared after it is shown'
    );
    [$status, , $headers] = $get('/account', $cookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'logged-out session cannot access the protected account page');

    [$status, $body] = $get('/login', $cookie);
    $rememberLoginToken = $csrfToken($body);
    [$status, , $headers] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'remember_me' => '1',
        '_csrf_token' => $rememberLoginToken,
    ], $cookie);
    $assert($status === 303 && in_array('Location: /dashboard', $headers, true), 'remembered login succeeds');

    $rememberSetCookie = '';
    foreach ($headers as $header) {
        if (preg_match('/^Set-Cookie:\s*VSAI_REMEMBER=[a-f0-9]{64};/i', $header) === 1) {
            $rememberSetCookie = $header;
            break;
        }
    }
    $assert(
        $rememberSetCookie !== ''
            && stripos($rememberSetCookie, 'HttpOnly') !== false
            && stripos($rememberSetCookie, 'SameSite=Lax') !== false,
        'persistent cookie is HTTP-only and same-site'
    );
    $assert(
        preg_match('/;\s*expires=([^;]+)/i', $rememberSetCookie, $cookieExpiry) === 1
            && strtotime($cookieExpiry[1]) >= time() + (89 * 24 * 60 * 60),
        'persistent browser cookie has a 90-day expiration'
    );

    $rememberCookie = '';
    foreach (explode(';', $cookie) as $cookiePair) {
        if (str_starts_with(trim($cookiePair), 'VSAI_REMEMBER=')) {
            $rememberCookie = trim($cookiePair);
            break;
        }
    }
    $assert(
        preg_match('/\AVSAI_REMEMBER=([a-f0-9]{64})\z/D', $rememberCookie, $rememberMatch) === 1,
        'remembered login issues a random persistent cookie'
    );

    $rawRememberToken = $rememberMatch[1];
    $rememberHash = hash('sha256', $rawRememberToken);
    $rememberTokenQuery = $pdo->prepare(
        'SELECT `token_hash`, `expires_at`, `last_used_at`
         FROM `remember_tokens`
         WHERE `token_hash` = :token_hash'
    );
    $rememberTokenQuery->execute(['token_hash' => $rememberHash]);
    $rememberTokenRecord = $rememberTokenQuery->fetch();
    $assert(
        is_array($rememberTokenRecord)
            && hash_equals((string) $rememberTokenRecord['token_hash'], $rememberHash)
            && !hash_equals((string) $rememberTokenRecord['token_hash'], $rawRememberToken),
        'database stores only the persistent token hash'
    );
    $assert(
        is_array($rememberTokenRecord)
            && strtotime((string) $rememberTokenRecord['expires_at']) >= time() + (89 * 24 * 60 * 60),
        'persistent token expires in approximately 90 days'
    );

    $rememberCookieOnly = $rememberCookie;
    [$status, $body] = $get('/account', $rememberCookieOnly);
    $assert(
        $status === 200 && str_contains($body, $temporaryEmail)
            && str_contains($rememberCookieOnly, 'VSAI_SESSION='),
        'valid remember token restores an authenticated session after the session cookie is lost'
    );
    $rotatedRememberCookie = '';
    foreach (explode(';', $rememberCookieOnly) as $cookiePair) {
        if (str_starts_with(trim($cookiePair), 'VSAI_REMEMBER=')) {
            $rotatedRememberCookie = trim($cookiePair);
            break;
        }
    }
    $assert(
        preg_match('/\AVSAI_REMEMBER=([a-f0-9]{64})\z/D', $rotatedRememberCookie, $rotatedMatch) === 1
            && $rotatedMatch[1] !== $rawRememberToken,
        'remember token rotates when it restores a session'
    );
    $rotatedRememberHash = hash('sha256', $rotatedMatch[1]);
    $rememberTokenQuery->execute(['token_hash' => $rememberHash]);
    $assert($rememberTokenQuery->fetch() === false, 'previous remember token is invalidated on rotation');
    $rememberTokenQuery->execute(['token_hash' => $rotatedRememberHash]);
    $restoredToken = $rememberTokenQuery->fetch();
    $assert(
        is_array($restoredToken) && $restoredToken['last_used_at'] !== null,
        'remember token use is recorded'
    );

    $accountToken = $csrfToken($body);
    [, , $headers] = $post('/logout', ['_csrf_token' => $accountToken], $rememberCookieOnly);
    $assert(in_array('Location: /', $headers, true), 'logout succeeds from a restored remembered session');
    $rememberTokenQuery->execute(['token_hash' => $rotatedRememberHash]);
    $assert($rememberTokenQuery->fetch() === false, 'logout revokes the remembered login token');

    [$status, , $headers] = $get('/account', $rememberCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true),
        'rotated remember token cannot restore access'
    );
    [$status, , $headers] = $get('/account', $rotatedRememberCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true),
        'logged-out remember token cannot restore access'
    );

    $clearTestBuckets = $pdo->prepare(
        'DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash'
    );
    foreach ($testBuckets as $bucket) {
        $clearTestBuckets->execute(['bucket_hash' => $bucket]);
    }

    $throttleCookie = '';
    [$status, $body] = $get('/login', $throttleCookie);
    $loginToken = $csrfToken($body);
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        [$status] = $post('/login', [
            'email' => $temporaryEmail,
            'password' => 'Incorrect-Password',
            '_csrf_token' => $loginToken,
        ], $throttleCookie);
    }
    $assert($status === 422, "failed login attempts are recorded up to the rate limit (HTTP {$status})");

    $ageLimitWindow = $pdo->prepare(
        'UPDATE `auth_login_rate_limits`
         SET `window_started_at` = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 16 MINUTE)
         WHERE `bucket_hash` = :bucket_hash'
    );
    foreach ($testBuckets as $bucket) {
        $ageLimitWindow->execute(['bucket_hash' => $bucket]);
    }

    [$status, $body] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => $loginToken,
    ], $throttleCookie);
    $assert(
        $status === 429 && str_contains($body, 'Too many sign-in attempts'),
        'login throttling persists for its full lock period'
    );

    $registrationThrottleCookie = '';
    [$status, $body] = $get('/register', $registrationThrottleCookie);
    $registrationThrottleToken = $csrfToken($body);
    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $registrationThrottleToken,
    ], $registrationThrottleCookie);
    $assert(
        $status === 422,
        "registration attempts are counted up to the rate limit (HTTP {$status})"
    );
    [$status, $body] = $post('/register', [
        'name' => $temporaryName,
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        'password_confirmation' => $temporaryPassword,
        'terms_accepted' => '1',
        '_csrf_token' => $registrationThrottleToken,
    ], $registrationThrottleCookie);
    $assert(
        $status === 429 && str_contains($body, 'Too many registration attempts'),
        'registration throttling returns a safe retry message'
    );

    $httpResetToken = bin2hex(random_bytes(32));
    $insertHttpResetToken = $pdo->prepare(
        'INSERT INTO `password_reset_tokens` (`user_id`, `token_hash`, `expires_at`)
         VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
    );
    $insertHttpResetToken->execute([
        'user_id' => $user['id'],
        'token_hash' => hash('sha256', $httpResetToken),
    ]);
    $resetCookie = '';
    [$status, $body, $headers] = $get('/reset-password?token=' . $httpResetToken, $resetCookie);
    $assert(
        $status === 200
            && str_contains($body, 'action="/reset-password"')
            && str_contains($body, 'name="password_confirmation"')
            && in_array('Referrer-Policy: no-referrer', $headers, true),
        'valid reset link displays the protected password form without referrer leakage'
    );
    [$status, $body] = $post('/reset-password', [
        'token' => $httpResetToken,
        'password' => 'NewPassword7',
        'password_confirmation' => 'DifferentPassword7',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert($status === 422 && str_contains($body, 'password confirmation does not match'), 'reset form rejects mismatched passwords');
    [$status, $body, $headers] = $post('/reset-password', [
        'token' => $httpResetToken,
        'password' => 'NewPassword7',
        'password_confirmation' => 'NewPassword7',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true),
        'successful password reset redirects to login without signing the user in'
    );
    [$status, $body, $headers] = $get('/dashboard', $resetCookie);
    $assert($status === 303 && in_array('Location: /login', $headers, true), 'password reset does not automatically authenticate the user');
    foreach ($testBuckets as $bucket) {
        $clearAttemptBucket->execute(['bucket_hash' => $bucket]);
    }
    [$status, $body] = $get('/login', $resetCookie);
    $assert(
        $status === 200 && str_contains($body, 'Your password has been updated'),
        'password reset success notification is shown on login'
    );
    $loginToken = $csrfToken($body);
    [$status, $body] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => $temporaryPassword,
        '_csrf_token' => $loginToken,
    ], $resetCookie);
    $assert($status === 422 && str_contains($body, 'email or password is incorrect'), 'old password cannot log in after the reset route');
    [$status, , $headers] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => 'NewPassword7',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert(
        $status === 303 && in_array('Location: /dashboard', $headers, true),
        'new password can log in after the reset route'
    );

    [$status, $body] = $get('/profile', $resetCookie);
    $assert(
        $status === 200
            && str_contains($body, 'Your profile')
            && str_contains($body, $temporaryName)
            && str_contains($body, $temporaryEmail)
            && str_contains($body, 'Account created')
            && str_contains($body, 'Active')
            && !str_contains($body, 'name="email"'),
        'profile displays account details without allowing unverified email changes'
    );
    $profileCsrf = $csrfToken($body);
    [$status] = $post('/profile', ['name' => 'Changed Name', '_csrf_token' => 'invalid'], $resetCookie);
    $assert($status === 419, 'profile update rejects an invalid CSRF token');
    [$status, , $headers] = $post('/profile', [
        'name' => 'Updated Studio Name',
        '_csrf_token' => $profileCsrf,
    ], $resetCookie);
    $assert(
        $status === 303 && in_array('Location: /profile', $headers, true),
        'profile name update succeeds'
    );
    [$status, $body] = $get('/profile', $resetCookie);
    $assert(
        $status === 200
            && str_contains($body, 'Updated Studio Name')
            && str_contains($body, $temporaryEmail)
            && str_contains($body, 'Your profile has been updated.'),
        'profile update changes the name and preserves verified account details'
    );
    $profileActivityCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type'
    );
    $profileActivityCount->execute([
        'user_id' => $user['id'],
        'activity_type' => 'profile_updated',
    ]);
    $assert((int) $profileActivityCount->fetchColumn() === 1, 'successful profile updates are recorded once');

    $otherCookie = '';
    [$status, $body] = $get('/login', $otherCookie);
    [$status, , $headers] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => 'NewPassword7',
        'remember_me' => '1',
        '_csrf_token' => $csrfToken($body),
    ], $otherCookie);
    $assert(
        $status === 303 && in_array('Location: /dashboard', $headers, true)
            && str_contains($otherCookie, 'VSAI_REMEMBER='),
        'second authenticated session is created for session invalidation coverage'
    );

    [$status, $body] = $get('/change-password', $resetCookie);
    $assert(
        $status === 200
            && str_contains($body, 'Current password')
            && str_contains($body, 'Confirm new password'),
        'change-password page displays all required fields'
    );
    $changeCsrf = $csrfToken($body);
    [$status] = $post('/change-password', [
        'current_password' => 'NewPassword7',
        'password' => 'ChangedPassword8',
        'password_confirmation' => 'ChangedPassword8',
        '_csrf_token' => 'invalid',
    ], $resetCookie);
    $assert($status === 419, 'password change rejects an invalid CSRF token');
    [$status, $body] = $post('/change-password', [
        'current_password' => 'WrongCurrent7',
        'password' => 'ChangedPassword8',
        'password_confirmation' => 'ChangedPassword8',
        '_csrf_token' => $changeCsrf,
    ], $resetCookie);
    $assert(
        $status === 422 && str_contains($body, 'The current password is incorrect.'),
        'password change rejects an incorrect current password'
    );
    [$status, $body] = $post('/change-password', [
        'current_password' => 'NewPassword7',
        'password' => 'ChangedPassword8',
        'password_confirmation' => 'DifferentPassword8',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert(
        $status === 422 && str_contains($body, 'password confirmation does not match'),
        'password change rejects a confirmation mismatch'
    );
    [$status, $body] = $post('/change-password', [
        'current_password' => 'NewPassword7',
        'password' => 'NewPassword7',
        'password_confirmation' => 'NewPassword7',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert(
        $status === 422 && str_contains($body, 'different from your current password'),
        'password change prevents reusing the current password'
    );

    $previousSessionId = '';
    if (preg_match('/(?:^|;\s*)VSAI_SESSION=([^;]+)/', $resetCookie, $sessionMatch) === 1) {
        $previousSessionId = $sessionMatch[1];
    }
    [$status, , $headers] = $post('/change-password', [
        'current_password' => 'NewPassword7',
        'password' => 'ChangedPassword8',
        'password_confirmation' => 'ChangedPassword8',
        '_csrf_token' => $csrfToken($body),
    ], $resetCookie);
    $assert(
        $status === 303 && in_array('Location: /profile', $headers, true),
        'valid password change succeeds and redirects to profile'
    );
    $passwordActivityCount = $pdo->prepare(
        'SELECT COUNT(*) FROM `user_activities`
         WHERE `user_id` = :user_id AND `activity_type` = :activity_type'
    );
    $passwordActivityCount->execute([
        'user_id' => $user['id'],
        'activity_type' => 'password_changed',
    ]);
    $assert((int) $passwordActivityCount->fetchColumn() === 1, 'successful password changes create an account notification');
    $nextSessionId = '';
    if (preg_match('/(?:^|;\s*)VSAI_SESSION=([^;]+)/', $resetCookie, $sessionMatch) === 1) {
        $nextSessionId = $sessionMatch[1];
    }
    $assert(
        $previousSessionId !== '' && $nextSessionId !== '' && $previousSessionId !== $nextSessionId,
        'password change rotates the current session identifier'
    );
    [$status, $body] = $get('/profile', $resetCookie);
    $assert(
        $status === 200
            && str_contains($body, 'Updated Studio Name')
            && str_contains($body, 'Your password has been changed'),
        'current session remains authenticated after its password change'
    );
    [$status, , $headers] = $get('/dashboard', $otherCookie);
    $assert(
        $status === 303 && in_array('Location: /login', $headers, true),
        'password change invalidates other authenticated sessions'
    );
    $remainingRememberTokens = $pdo->prepare(
        'SELECT COUNT(*) FROM `remember_tokens` WHERE `user_id` = :user_id'
    );
    $remainingRememberTokens->execute(['user_id' => $user['id']]);
    $assert(
        (int) $remainingRememberTokens->fetchColumn() === 0,
        'password change revokes all persistent remembered logins'
    );
    $versionQuery = $pdo->prepare('SELECT `auth_version` FROM `users` WHERE `id` = :id');
    $versionQuery->execute(['id' => $user['id']]);
    $assert((int) $versionQuery->fetchColumn() >= 3, 'password changes increment the per-user session version');

    foreach ($passwordChangeTestBuckets as $bucket) {
        $clearAttemptBucket->execute(['bucket_hash' => $bucket]);
    }
    $verifyPasswordCookie = '';
    [$status, $body] = $get('/login', $verifyPasswordCookie);
    [$status, $body] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => 'NewPassword7',
        '_csrf_token' => $csrfToken($body),
    ], $verifyPasswordCookie);
    $assert(
        $status === 422 && str_contains($body, 'email or password is incorrect'),
        'old password cannot log in after an authenticated password change'
    );
    [$status, , $headers] = $post('/login', [
        'email' => $temporaryEmail,
        'password' => 'ChangedPassword8',
        '_csrf_token' => $csrfToken($body),
    ], $verifyPasswordCookie);
    $assert(
        $status === 303 && in_array('Location: /dashboard', $headers, true),
        'new password can log in after an authenticated password change'
    );

    fwrite(STDOUT, sprintf("All %d authentication checks passed.\n", $passed));
} finally {
    if (isset($user['id'])) {
        $deleteUser = $pdo->prepare('DELETE FROM `users` WHERE `id` = :id');
        $deleteUser->execute(['id' => $user['id']]);
    } else {
        $deleteUser = $pdo->prepare('DELETE FROM `users` WHERE `email` = :email');
        $deleteUser->execute(['email' => $temporaryEmail]);
    }

    $deleteDisabledUser = $pdo->prepare('DELETE FROM `users` WHERE `email` = :email');
    $deleteDisabledUser->execute(['email' => $disabledEmail]);

    $deleteBuckets = $pdo->prepare('DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash');
    foreach ($testBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ($additionalLoginTestBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ($registrationTestBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ($passwordResetTestBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ($passwordChangeTestBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }
    foreach ($verificationRequestBuckets as $bucket) {
        $deleteBuckets->execute(['bucket_hash' => $bucket]);
    }

    $deleteRememberToken = $pdo->prepare(
        'DELETE FROM `remember_tokens` WHERE `token_hash` = :token_hash'
    );
    foreach (array_unique($rememberTokenHashes) as $tokenHash) {
        $deleteRememberToken->execute(['token_hash' => $tokenHash]);
    }

    $sessionDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    foreach (array_unique($sessionIds) as $sessionId) {
        $sessionFile = $sessionDirectory . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;

        if (is_file($sessionFile)) {
            unlink($sessionFile);
        }
    }

    proc_terminate($server);
    proc_close($server);
    @unlink($serverLog);
}
