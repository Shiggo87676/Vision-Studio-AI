<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Environment::required('DB_HOST');
        $port = Environment::required('DB_PORT');
        $database = Environment::required('DB_DATABASE');
        $username = Environment::required('DB_USERNAME');
        $password = Environment::get('DB_PASSWORD') ?? '';

        if (!preg_match('/^[A-Za-z0-9._:-]+$/D', $host)) {
            throw new RuntimeException('DB_HOST contains invalid characters.');
        }

        if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new RuntimeException('DB_PORT must be a valid TCP port.');
        }

        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $database)) {
            throw new RuntimeException('DB_DATABASE must be a valid MySQL database name.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
