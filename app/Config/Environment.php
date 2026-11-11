<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Environment file is missing or unreadable: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException("Unable to read environment file: {$path}");
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                throw new RuntimeException(
                    sprintf('Invalid environment entry on line %d.', $lineNumber + 1)
                );
            }

            $name = $matches[1];

            if (getenv($name) !== false) {
                continue;
            }

            $value = trim($matches[2]);

            if (strlen($value) >= 2) {
                $quote = $value[0];

                if (($quote === '"' || $quote === "'") && $value[strlen($value) - 1] === $quote) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    public static function get(string $name): ?string
    {
        $value = getenv($name);

        if ($value !== false) {
            return $value;
        }

        return isset($_ENV[$name]) ? (string) $_ENV[$name] : null;
    }

    public static function required(string $name): string
    {
        $value = self::get($name);

        if ($value === null || $value === '') {
            throw new RuntimeException("Required environment variable {$name} is not set.");
        }

        return $value;
    }
}
