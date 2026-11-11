<?php

declare(strict_types=1);

use App\Config\Environment;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$passed = 0;

$assert = static function (bool $condition, string $message) use (&$passed): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }

    $passed++;
    fwrite(STDOUT, "PASS {$message}\n");
};

$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vision-studio-ai-' . bin2hex(random_bytes(6));

if (!mkdir($temporaryDirectory, 0700)) {
    throw new RuntimeException('Unable to create temporary test directory.');
}

$environmentFile = $temporaryDirectory . DIRECTORY_SEPARATOR . '.env';
$environmentName = 'VISION_STUDIO_AI_TEST_' . strtoupper(bin2hex(random_bytes(4)));
$environmentContents = "# environment test\n{$environmentName}=\"value with spaces\"\n";

if (file_put_contents($environmentFile, $environmentContents) === false) {
    throw new RuntimeException('Unable to create temporary environment file.');
}

try {
    Environment::load($environmentFile);
    $assert(Environment::get($environmentName) === 'value with spaces', 'environment values are loaded');
    $assert(Environment::required($environmentName) === 'value with spaces', 'required environment values are returned');

    $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    $previousUri = $_SERVER['REQUEST_URI'] ?? null;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    ob_start();
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
    $homepage = ob_get_clean();
    if ($previousMethod === null) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $previousMethod;
    }
    if ($previousUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $previousUri;
    }

    $assert(is_string($homepage) && str_contains($homepage, 'Vision Studio AI'), 'homepage displays the brand');
    $assert(
        is_string($homepage)
            && str_contains($homepage, 'href="/#top">Home</a>')
            && str_contains($homepage, 'href="/#about">About</a>')
            && str_contains($homepage, 'href="/#features">Features</a>')
            && str_contains($homepage, 'href="/login">Log in</a>')
            && str_contains($homepage, 'href="/register">Get started'),
        'homepage navigation includes home, about, features, login, and get started'
    );
    $assert(
        is_string($homepage)
            && str_contains($homepage, 'id="features"')
            && str_contains($homepage, 'id="about"')
            && str_contains($homepage, 'site-footer'),
        'homepage includes feature, about, and footer sections'
    );
} finally {
    unset($_ENV[$environmentName], $_SERVER[$environmentName]);
    putenv($environmentName);
    unlink($environmentFile);
    rmdir($temporaryDirectory);
}

fwrite(STDOUT, sprintf("All %d checks passed.\n", $passed));
