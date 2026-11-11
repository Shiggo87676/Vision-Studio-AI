<?php

declare(strict_types=1);

use App\Config\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Migration runner is available only from the command line.\n");
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `migrations` (
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT UNSIGNED NOT NULL,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`migration`),
        KEY `idx_migrations_batch` (`batch`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob(__DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.php');

if ($files === false) {
    throw new RuntimeException('Unable to read database migrations.');
}

sort($files, SORT_STRING);
$appliedMigrations = $pdo->query('SELECT `migration` FROM `migrations`')->fetchAll(PDO::FETCH_COLUMN);
$appliedMigrations = array_fill_keys($appliedMigrations, true);
$nextBatch = (int) $pdo->query('SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`')->fetchColumn();
$newMigrations = 0;

foreach ($files as $file) {
    $migrationName = basename($file);

    if (isset($appliedMigrations[$migrationName])) {
        continue;
    }

    $statements = require $file;

    if (!is_array($statements) || $statements === []) {
        throw new RuntimeException("Migration {$migrationName} must return one or more SQL statements.");
    }

    foreach ($statements as $statement) {
        if (!is_string($statement) || trim($statement) === '') {
            throw new RuntimeException("Migration {$migrationName} contains an invalid SQL statement.");
        }
    }

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $record = $pdo->prepare('INSERT INTO `migrations` (`migration`, `batch`) VALUES (:migration, :batch)');
    $record->execute([
        'migration' => $migrationName,
        'batch' => $nextBatch,
    ]);

    $newMigrations++;
    fwrite(STDOUT, "Applied {$migrationName}\n");
}

if ($newMigrations === 0) {
    fwrite(STDOUT, "Database is already up to date.\n");
} else {
    fwrite(STDOUT, sprintf("Applied %d migration(s).\n", $newMigrations));
}
