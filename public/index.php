<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Controllers\AuthController;
use App\Services\AuthService;

$auth = new AuthController(new AuthService());
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($requestPath) ? $requestPath : '/';
$routes = [
    'GET /' => [$auth, 'home'],
    'GET /register' => [$auth, 'showRegister'],
    'POST /register' => [$auth, 'register'],
    'GET /verify-email' => [$auth, 'showVerifyEmail'],
    'POST /verify-email' => [$auth, 'verifyEmail'],
    'POST /verify-email/resend' => [$auth, 'requestEmailVerification'],
    'GET /login' => [$auth, 'showLogin'],
    'POST /login' => [$auth, 'login'],
    'GET /forgot-password' => [$auth, 'showForgotPassword'],
    'POST /forgot-password' => [$auth, 'requestPasswordReset'],
    'GET /reset-password' => [$auth, 'showResetPassword'],
    'POST /reset-password' => [$auth, 'resetPassword'],
    'POST /logout' => [$auth, 'logout'],
    'GET /account' => [$auth, 'account'],
    'GET /dashboard' => [$auth, 'dashboard'],
    'GET /settings' => [$auth, 'showSettings'],
    'POST /notifications/read' => [$auth, 'markNotificationsRead'],
    'GET /profile' => [$auth, 'showProfile'],
    'POST /profile' => [$auth, 'updateProfile'],
    'GET /change-password' => [$auth, 'showChangePassword'],
    'POST /change-password' => [$auth, 'changePassword'],
];
$handler = $routes[$method . ' ' . $path] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo 'Page not found.';
    return;
}

$handler();
