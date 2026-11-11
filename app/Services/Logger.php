<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class Logger
{
    public static function error(Throwable $exception): void
    {
        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the application log directory.');
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            gmdate('c'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        $entry = preg_replace('/(?<![a-f0-9])[a-f0-9]{64}(?![a-f0-9])/i', '[REDACTED-HEX-TOKEN]', $entry);

        if (!is_string($entry)) {
            throw new RuntimeException('Unable to redact sensitive values from the application log entry.');
        }

        if (file_put_contents($directory . DIRECTORY_SEPARATOR . 'app.log', $entry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the application log.');
        }
    }
}
