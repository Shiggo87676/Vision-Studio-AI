<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\User;
use PDO;
use Throwable;

final class AuthService
{
    private const REGISTRATION_MAX_ATTEMPTS = 10;

    private ?AuthRateLimiter $rateLimiter = null;

    /**
     * @return array{id: int, name: string, email: string}
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation,
        bool $termsAccepted,
        string $ipAddress
    ): array {
        $name = trim($name);
        $email = strtolower(trim($email));
        $limiter = new AuthRateLimiter(Database::connection(), self::REGISTRATION_MAX_ATTEMPTS);
        $buckets = $limiter->bucketsFor($this->rateLimitEmail($email, $ipAddress), $ipAddress, 'registration');

        if ($limiter->isLimited($buckets)) {
            throw new TooManyRegistrations('Too many registration attempts. Please try again in 15 minutes.');
        }

        $limiter->recordFailure($buckets);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Enter your full name.';
        } elseif (strlen($name) > 120 || preg_match('/[\r\n\x00-\x1F\x7F]/', $name) === 1
            || preg_match('/\A.{1,120}\z/us', $name) !== 1) {
            $errors['name'] = 'Enter a name between 1 and 120 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Enter your email address.';
        } elseif (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }

        $passwordCharacters = preg_match_all('/./us', $password);

        if ($password === '') {
            $errors['password'] = 'Enter a password.';
        } elseif ($passwordCharacters === false || $passwordCharacters < 7 || strlen($password) > 72) {
            $errors['password'] = 'Use at least 7 characters and no more than 72 bytes.';
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Confirm your password.';
        } elseif (!hash_equals($password, $passwordConfirmation)) {
            $errors['password_confirmation'] = 'The password confirmation does not match.';
        }

        if (!$termsAccepted) {
            $errors['terms'] = 'Accept the Terms and Conditions to create your account.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        if (User::emailExists($email)) {
            throw new ValidationException([
                'form' => 'We could not create your account with those details. Please review them and try again.',
            ]);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $user = User::create($name, $email, password_hash($password, PASSWORD_DEFAULT), $termsAccepted);

            if ($user === null) {
                throw new ValidationException([
                    'form' => 'We could not create your account with those details. Please review them and try again.',
                ]);
            }

            (new UserActivityService())->record((int) $user['id'], 'account_registered');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return $user;
    }

    /**
     * @return array{id: int, name: string, email: string, auth_version: int}|null
     */
    public function authenticate(string $email, string $password, string $ipAddress): ?array
    {
        $email = strtolower(trim($email));
        $limiter = $this->rateLimiter();
        $buckets = $limiter->bucketsFor($this->rateLimitEmail($email, $ipAddress), $ipAddress);

        if ($limiter->isLimited($buckets)) {
            throw new TooManyLoginAttempts('Too many sign-in attempts. Try again in 15 minutes.');
        }

        $validEmail = strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $user = $validEmail ? User::findByEmail($email) : null;

        if ($user === null) {
            static $dummyHash = null;
            $dummyHash ??= password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            password_verify($password, $dummyHash);
        }

        $passwordMatches = strlen($password) <= 72
            && $user !== null
            && password_verify($password, (string) $user['password']);

        if (!$passwordMatches || $user['status'] !== 'active' || $user['email_verified_at'] === null) {
            $limiter->recordFailure($buckets);

            return null;
        }

        $limiter->clearEmailBucket($email);

        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            User::updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'auth_version' => (int) $user['auth_version'],
        ];
    }

    /**
      * @return array{id: int, name: string, email: string, status: string, created_at: string, auth_version: int}|null
     */
    public function currentUser(): ?array
    {
        return \App\Middleware\AuthMiddleware::user();
    }

    private function rateLimiter(): AuthRateLimiter
    {
        return $this->rateLimiter ??= new AuthRateLimiter(Database::connection());
    }

    private function rateLimitEmail(string $email, string $ipAddress): string
    {
        if ($email !== '') {
            return $email;
        }

        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) === false ? 'unknown' : $ipAddress;

        return 'missing-email-' . hash('sha256', $ipAddress);
    }
}
