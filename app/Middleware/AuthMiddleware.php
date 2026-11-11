<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Session;
use App\Models\User;
use App\Services\RememberMe;

final class AuthMiddleware
{
    public static function user(): ?array
    {
        $userId = Session::userId();
        $restoredFromRememberCookie = false;

        if ($userId === null && PHP_SAPI !== 'cli') {
            $userId = (new RememberMe())->restoreUserId();
            $restoredFromRememberCookie = $userId !== null;
        }

        if ($userId === null) {
            return null;
        }

        $user = User::findActiveById($userId);

        if ($user === null) {
            if (PHP_SAPI !== 'cli') {
                (new RememberMe())->revokeCurrent();
            }

            Session::logOut();
            return null;
        }

        if ($restoredFromRememberCookie) {
            Session::logIn($userId, (int) $user['auth_version']);
        } elseif ((int) ($_SESSION['auth_version'] ?? 1) !== (int) $user['auth_version']) {
            if (PHP_SAPI !== 'cli') {
                (new RememberMe())->revokeCurrent();
            }

            Session::logOut();
            return null;
        }

        return $user;
    }
}
