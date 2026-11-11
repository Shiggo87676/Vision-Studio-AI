<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Session
{
    public const IDLE_TIMEOUT_SECONDS = 1800;
    public const ABSOLUTE_TIMEOUT_SECONDS = 43200;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $directory = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the session storage directory.');
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('The session storage directory is not writable.');
        }

        if (session_save_path($directory) === false) {
            throw new RuntimeException('Unable to configure session storage.');
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) self::ABSOLUTE_TIMEOUT_SECONDS);
        session_name('VSAI_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Unable to start the application session.');
        }

        $now = time();
        $createdAt = $_SESSION['created_at'] ?? null;
        $lastActivity = $_SESSION['last_activity'] ?? null;
        $hasAuthenticatedUser = isset($_SESSION['user_id']);
        $expired = ($hasAuthenticatedUser && !is_int($createdAt))
            || (is_int($createdAt) && $now - $createdAt >= self::ABSOLUTE_TIMEOUT_SECONDS)
            || (is_int($lastActivity) && $now - $lastActivity >= self::IDLE_TIMEOUT_SECONDS);

        if ($expired) {
            $_SESSION = [];

            if (!session_regenerate_id(true)) {
                throw new RuntimeException('Unable to rotate an expired session identifier.');
            }
        }

        if (!isset($_SESSION['created_at']) || !is_int($_SESSION['created_at'])) {
            $_SESSION['created_at'] = $now;
        }

        $_SESSION['last_activity'] = $now;
    }

    public static function userId(): ?int
    {
        if (PHP_SAPI === 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        self::start();
        $userId = $_SESSION['user_id'] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public static function logIn(int $userId, int $authVersion = 1): void
    {
        self::start();

        if ($userId < 1 || $authVersion < 1) {
            throw new \InvalidArgumentException('Authentication session values must be positive.');
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to regenerate the session identifier.');
        }

        $now = time();
        $_SESSION = [
            'user_id' => $userId,
            'auth_version' => $authVersion,
            'created_at' => $now,
            'last_activity' => $now,
        ];
    }

    public static function refreshAuthVersion(int $authVersion): void
    {
        self::start();

        if (!isset($_SESSION['user_id']) || $authVersion < 1) {
            throw new RuntimeException('Unable to refresh an unauthenticated session.');
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Unable to rotate the session identifier.');
        }

        $_SESSION['auth_version'] = $authVersion;
        $_SESSION['last_activity'] = time();
    }

    public static function logOut(?string $successMessage = null): void
    {
        self::start();
        $_SESSION = [];
        $parameters = session_get_cookie_params();

        if ($successMessage !== null) {
            setcookie('VSAI_NOTICE', 'logged_out', [
                'expires' => time() + 60,
                'path' => '/',
                'secure' => $parameters['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'],
            ]);
        }

        if (!session_destroy()) {
            throw new RuntimeException('Unable to invalidate the application session.');
        }
    }
}
