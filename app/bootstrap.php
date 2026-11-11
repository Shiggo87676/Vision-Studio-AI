<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Config\Session;
use App\Services\Logger;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Helpers'
    . DIRECTORY_SEPARATOR . 'functions.php';

$environmentFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';

if (is_file($environmentFile)) {
    Environment::load($environmentFile);
}

date_default_timezone_set('UTC');
error_reporting(E_ALL);

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    try {
        Logger::error($exception);
    } catch (\Throwable $loggingError) {
        error_log($loggingError->getMessage());
        error_log((string) $exception);
    }

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "An unexpected error occurred. Check storage/logs/app.log.\n");
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo 'An unexpected error occurred. Please try again later.';
});

if (PHP_SAPI !== 'cli') {
    Session::start();
}
