<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RememberToken;
use RuntimeException;

final class RememberMe
{
    private const COOKIE_NAME = 'VSAI_REMEMBER';
    private const LIFETIME_SECONDS = 90 * 24 * 60 * 60;

    public function issue(int $userId): void
    {
        $this->revokeCurrent();
        $token = bin2hex(random_bytes(32));
        RememberToken::create($userId, hash('sha256', $token));

        if (!setcookie(self::COOKIE_NAME, $token, $this->cookieOptions(time() + self::LIFETIME_SECONDS))) {
            RememberToken::revoke(hash('sha256', $token));
            throw new RuntimeException('Unable to set the persistent login cookie.');
        }

        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    public function restoreUserId(): ?int
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
            if ($token !== null) {
                $this->expireCookie();
            }

            return null;
        }

        $nextToken = bin2hex(random_bytes(32));
        $nextTokenHash = hash('sha256', $nextToken);
        $userId = RememberToken::rotate(hash('sha256', $token), $nextTokenHash);

        if ($userId === null) {
            $this->expireCookie();
            return null;
        }

        if (!setcookie(self::COOKIE_NAME, $nextToken, $this->cookieOptions(time() + self::LIFETIME_SECONDS))) {
            RememberToken::revoke($nextTokenHash);
            throw new RuntimeException('Unable to refresh the persistent login cookie.');
        }

        $_COOKIE[self::COOKIE_NAME] = $nextToken;

        return $userId;
    }

    public function revokeCurrent(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1) {
            RememberToken::revoke(hash('sha256', $token));
        }

        $this->expireCookie();
    }

    /**
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
     */
    private function cookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function expireCookie(): void
    {
        if (!setcookie(self::COOKIE_NAME, '', $this->cookieOptions(time() - 42000))) {
            throw new RuntimeException('Unable to clear the persistent login cookie.');
        }

        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
