<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Session;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use App\Services\PasswordChangeService;
use App\Services\PasswordResetService;
use App\Services\ProfileService;
use App\Services\RememberMe;
use App\Services\TooManyPasswordChanges;
use App\Services\TooManyPasswordResetRequests;
use App\Services\TooManyRegistrations;
use App\Services\TooManyLoginAttempts;
use App\Services\TooManyEmailVerificationRequests;
use App\Services\UserActivityService;
use App\Services\ValidationException;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function home(): void
    {
        $showLogoutConfirmation = ($_COOKIE['VSAI_NOTICE'] ?? '') === 'logged_out';

        if ($showLogoutConfirmation) {
            setcookie('VSAI_NOTICE', '', [
                'expires' => time() - 42000,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $this->render('home', [
            'pageTitle' => 'Vision Studio AI — Creative ideas, reimagined',
            'successMessage' => $showLogoutConfirmation ? 'You have been logged out successfully.' : '',
        ]);
    }

    public function showRegister(): void
    {
        if ($this->auth->currentUser() !== null) {
            $this->redirect('/dashboard');
        }

        $oldInput = $_SESSION['_old_input'] ?? [];
        $successMessage = $_SESSION['_flash_success'] ?? '';
        unset($_SESSION['_old_input']);
        unset($_SESSION['_flash_success']);
        $this->render('auth/register', [
            'oldInput' => $oldInput,
            'successMessage' => is_string($successMessage) ? $successMessage : '',
        ]);
    }

    public function register(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        $name = $this->postValue('name');
        $email = $this->postValue('email');
        $termsAccepted = $this->postValue('terms_accepted') === '1';

        try {
            $user = $this->auth->register(
                $name,
                $email,
                $this->postValue('password'),
                $this->postValue('password_confirmation'),
                $termsAccepted,
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (TooManyRegistrations $exception) {
            $this->render('auth/register', [
                'errors' => ['form' => $exception->getMessage()],
                'oldInput' => ['name' => $name, 'email' => $email],
                'termsAccepted' => $termsAccepted,
            ], 429);
            return;
        } catch (ValidationException $exception) {
            $this->render('auth/register', [
                'errors' => $exception->errors(),
                'oldInput' => ['name' => $name, 'email' => $email],
                'termsAccepted' => $termsAccepted,
            ], 422);
            return;
        }

        (new EmailVerificationService())->sendForUser((int) $user['id']);
        $_SESSION['_flash_success'] = 'Your account has been created. Check your email for a verification link before signing in.';
        $this->redirect('/register');
    }

    public function showVerifyEmail(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');

        $successMessage = $_SESSION['_flash_success'] ?? '';
        unset($_SESSION['_flash_success']);
        $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
        $tokenValid = $token !== '' && (new EmailVerificationService())->isValidToken($token);
        $this->render('auth/verify-email', [
            'pageTitle' => 'Verify your email — Vision Studio AI',
            'successMessage' => is_string($successMessage) ? $successMessage : '',
            'token' => $token,
            'emailVerificationTokenValid' => $tokenValid,
        ], $token !== '' && !$tokenValid ? 422 : 200);
    }

    public function verifyEmail(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        $token = $this->postValue('token');

        if (!(new EmailVerificationService())->verify($token)) {
            $this->render('auth/verify-email', [
                'pageTitle' => 'Verify your email — Vision Studio AI',
                'message' => 'This verification link is invalid, expired, or has already been used. Request a new link to continue.',
                'token' => '',
                'emailVerificationTokenValid' => false,
            ], 422);
            return;
        }

        $_SESSION['_flash_success'] = 'Your email address is verified. You can now sign in.';
        $this->redirect('/login');
    }

    public function requestEmailVerification(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        try {
            (new EmailVerificationService())->request(
                $this->postValue('email'),
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (TooManyEmailVerificationRequests $exception) {
            $this->render('auth/verify-email', [
                'pageTitle' => 'Verify your email — Vision Studio AI',
                'errors' => ['form' => $exception->getMessage()],
                'emailVerificationTokenValid' => false,
            ], 429);
            return;
        }

        $_SESSION['_flash_success'] = 'If that address belongs to an unverified account, a verification link will be sent shortly.';
        $this->redirect('/verify-email');
    }

    public function showLogin(): void
    {
        if ($this->auth->currentUser() !== null) {
            $this->redirect('/dashboard');
        }

        $oldInput = $_SESSION['_old_input'] ?? [];
        $successMessage = $_SESSION['_flash_success'] ?? '';
        $passwordResetNotice = ($_COOKIE['VSAI_NOTICE'] ?? '') === 'password_reset';

        if ($passwordResetNotice) {
            setcookie('VSAI_NOTICE', '', [
                'expires' => time() - 42000,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        unset($_SESSION['_old_input']);
        unset($_SESSION['_flash_success']);
        $this->render('auth/login', [
            'oldInput' => $oldInput,
            'successMessage' => is_string($successMessage) && $successMessage !== ''
                ? $successMessage
                : ($passwordResetNotice ? 'Your password has been updated. You can now log in with your new password.' : ''),
        ]);
    }

    public function showForgotPassword(): void
    {
        $successMessage = $_SESSION['_flash_success'] ?? '';
        unset($_SESSION['_flash_success']);
        $this->render('auth/forgot-password', [
            'pageTitle' => 'Forgot password — Vision Studio AI',
            'successMessage' => is_string($successMessage) ? $successMessage : '',
        ]);
    }

    public function requestPasswordReset(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        $email = $this->postValue('email');

        try {
            (new PasswordResetService())->request(
                $email,
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (TooManyPasswordResetRequests $exception) {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Forgot password — Vision Studio AI',
                'errors' => ['form' => $exception->getMessage()],
                'oldInput' => ['email' => $email],
            ], 429);
            return;
        } catch (ValidationException $exception) {
            $this->render('auth/forgot-password', [
                'pageTitle' => 'Forgot password — Vision Studio AI',
                'errors' => $exception->errors(),
                'oldInput' => ['email' => $email],
            ], 422);
            return;
        }

        $_SESSION['_flash_success'] = 'If an account matches that email address, a password reset link will be sent shortly.';
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
        $tokenValid = (new PasswordResetService())->isValidToken($token);
        $this->render('auth/reset-password', [
            'pageTitle' => 'Reset password — Vision Studio AI',
            'token' => $token,
            'resetTokenValid' => $tokenValid,
        ]);
    }

    public function resetPassword(): void
    {
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        $token = $this->postValue('token');
        $reset = new PasswordResetService();

        try {
            $completed = $reset->reset(
                $token,
                $this->postValue('password'),
                $this->postValue('password_confirmation')
            );
        } catch (ValidationException $exception) {
            $this->render('auth/reset-password', [
                'pageTitle' => 'Reset password — Vision Studio AI',
                'errors' => $exception->errors(),
                'token' => $token,
                'resetTokenValid' => $reset->isValidToken($token),
            ], 422);
            return;
        }

        if (!$completed) {
            $this->render('auth/reset-password', [
                'pageTitle' => 'Reset password — Vision Studio AI',
                'message' => 'This reset link is invalid, expired, or has already been used. Request a new link to continue.',
                'token' => $token,
                'resetTokenValid' => false,
            ], 422);
            return;
        }

        if (isset($_SESSION['user_id'])) {
            Session::logOut();
            setcookie('VSAI_NOTICE', 'password_reset', [
                'expires' => time() + 60,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            $_SESSION['_flash_success'] = 'Your password has been updated. You can now log in with your new password.';
        }

        $this->redirect('/login');
    }

    public function login(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        $email = $this->postValue('email');

        try {
            $user = $this->auth->authenticate(
                $email,
                $this->postValue('password'),
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (TooManyLoginAttempts $exception) {
            $this->render('auth/login', [
                'errors' => ['form' => $exception->getMessage()],
                'oldInput' => ['email' => $email],
                'rememberMe' => $this->postValue('remember_me') === '1',
            ], 429);
            return;
        }

        if ($user === null) {
            $this->render('auth/login', [
                'errors' => ['form' => 'The email or password is incorrect.'],
                'oldInput' => ['email' => $email],
                'rememberMe' => $this->postValue('remember_me') === '1',
            ], 422);
            return;
        }

        Session::logIn($user['id'], $user['auth_version']);
        $rememberMe = new RememberMe();

        if ($this->postValue('remember_me') === '1') {
            $rememberMe->issue($user['id']);
        } else {
            $rememberMe->revokeCurrent();
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        (new RememberMe())->revokeCurrent();
        Session::logOut('You have been logged out successfully.');
        $this->redirect('/');
    }

    public function account(): void
    {
        $this->dashboard();
    }

    public function dashboard(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        $activity = new UserActivityService();
        $this->render('account', [
            'currentUser' => $user,
            'pageTitle' => 'Dashboard — Vision Studio AI',
            'dashboardLayout' => true,
            'activities' => $activity->recentForUser((int) $user['id']),
            'notifications' => $activity->notificationsForUser((int) $user['id']),
            'unreadNotificationCount' => $activity->unreadNotificationCount((int) $user['id']),
            'activityCount' => $activity->activityCountForUser((int) $user['id']),
        ]);
    }

    public function markNotificationsRead(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        (new UserActivityService())->markNotificationsRead((int) $user['id']);
        $this->redirect('/dashboard');
    }

    public function showSettings(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        $this->render('settings', [
            'currentUser' => $user,
            'pageTitle' => 'Settings — Vision Studio AI',
            'dashboardLayout' => true,
            'activePage' => 'settings',
        ]);
    }

    public function showProfile(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        $successMessage = $_SESSION['_flash_success'] ?? '';
        unset($_SESSION['_flash_success']);
        $this->render('profile', [
            'currentUser' => $user,
            'pageTitle' => 'Profile — Vision Studio AI',
            'successMessage' => is_string($successMessage) ? $successMessage : '',
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        try {
            (new ProfileService())->updateName((int) $user['id'], $this->postValue('name'));
        } catch (ValidationException $exception) {
            $this->render('profile', [
                'currentUser' => $user,
                'pageTitle' => 'Profile — Vision Studio AI',
                'errors' => $exception->errors(),
                'oldInput' => ['name' => $this->postValue('name')],
            ], 422);
            return;
        }

        $_SESSION['_flash_success'] = 'Your profile has been updated.';
        $this->redirect('/profile');
    }

    public function showChangePassword(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        $this->render('auth/change-password', [
            'currentUser' => $user,
            'pageTitle' => 'Change password — Vision Studio AI',
        ]);
    }

    public function changePassword(): void
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        if (!$this->hasValidCsrfToken()) {
            $this->renderMessage(419, 'Your form session expired. Reload the page and try again.');
            return;
        }

        try {
            $authVersion = (new PasswordChangeService())->change(
                (int) $user['id'],
                $this->postValue('current_password'),
                $this->postValue('password'),
                $this->postValue('password_confirmation'),
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            );
        } catch (TooManyPasswordChanges $exception) {
            $this->render('auth/change-password', [
                'currentUser' => $user,
                'pageTitle' => 'Change password — Vision Studio AI',
                'errors' => ['form' => $exception->getMessage()],
            ], 429);
            return;
        } catch (ValidationException $exception) {
            $this->render('auth/change-password', [
                'currentUser' => $user,
                'pageTitle' => 'Change password — Vision Studio AI',
                'errors' => $exception->errors(),
            ], 422);
            return;
        }

        Session::refreshAuthVersion($authVersion);
        $_SESSION['_flash_success'] = 'Your password has been changed. Other active sessions have been signed out.';
        $this->redirect('/profile');
    }

    private function render(string $view, array $data = [], int $status = 200): void
    {
        $contentFile = BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!is_file($contentFile)) {
            throw new \RuntimeException('The requested page view does not exist.');
        }

        http_response_code($status);
        $pageTitle = $data['pageTitle'] ?? 'Vision Studio AI';
        $currentUser = $data['currentUser'] ?? $this->auth->currentUser();
        $errors = $data['errors'] ?? [];
        $oldInput = $data['oldInput'] ?? [];
        $rememberMe = $data['rememberMe'] ?? false;
        $termsAccepted = $data['termsAccepted'] ?? false;
        $successMessage = $data['successMessage'] ?? '';
        $message = $data['message'] ?? '';
        $email = $data['email'] ?? '';
        $token = $data['token'] ?? '';
        $resetTokenValid = $data['resetTokenValid'] ?? false;
        $emailVerificationTokenValid = $data['emailVerificationTokenValid'] ?? false;
        $dashboardLayout = $data['dashboardLayout'] ?? false;
        $activities = $data['activities'] ?? [];
        $notifications = $data['notifications'] ?? [];
        $unreadNotificationCount = $data['unreadNotificationCount'] ?? 0;
        $activityCount = $data['activityCount'] ?? 0;
        $activePage = $data['activePage'] ?? 'dashboard';
        require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR
            . 'layouts' . DIRECTORY_SEPARATOR . 'main.php';
    }

    private function renderMessage(int $status, string $message): void
    {
        $this->render('errors/message', ['message' => $message], $status);
    }

    private function hasValidCsrfToken(): bool
    {
        $submittedToken = $this->postValue('_csrf_token');
        $sessionToken = isset($_SESSION['_csrf_token']) && is_string($_SESSION['_csrf_token'])
            ? $_SESSION['_csrf_token']
            : '';

        return $sessionToken !== '' && $submittedToken !== '' && hash_equals($sessionToken, $submittedToken);
    }

    private function postValue(string $key): string
    {
        $value = $_POST[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
