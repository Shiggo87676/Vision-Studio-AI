<?php

declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$publicDirectory = realpath(__DIR__);
$requestedFile = is_string($requestPath)
    ? realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rawurldecode($requestPath)))
    : false;

if ($publicDirectory !== false && $requestedFile !== false
    && is_file($requestedFile)
    && str_starts_with(strtolower($requestedFile), strtolower($publicDirectory . DIRECTORY_SEPARATOR))) {
    return false;
}

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
