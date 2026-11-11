<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;

final class RememberToken
{
    public static function create(int $userId, string $tokenHash): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'DELETE FROM `remember_tokens`
             WHERE `user_id` = :user_id AND `expires_at` <= CURRENT_TIMESTAMP'
        )->execute(['user_id' => $userId]);

        $statement = $pdo->prepare(
            'INSERT INTO `remember_tokens` (`user_id`, `token_hash`, `expires_at`)
             VALUES (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 90 DAY))'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);
    }

    public static function rotate(string $currentTokenHash, string $nextTokenHash): ?int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'SELECT `remember_tokens`.`user_id`
                 FROM `remember_tokens`
                 INNER JOIN `users` ON `users`.`id` = `remember_tokens`.`user_id`
                 WHERE `remember_tokens`.`token_hash` = :token_hash
                   AND `remember_tokens`.`expires_at` > CURRENT_TIMESTAMP
                   AND `users`.`status` = :status
                 LIMIT 1'
                . ' FOR UPDATE'
            );
            $statement->execute([
                'token_hash' => $currentTokenHash,
                'status' => 'active',
            ]);
            $userId = $statement->fetchColumn();

            if ($userId === false) {
                $delete = $pdo->prepare(
                    'DELETE FROM `remember_tokens` WHERE `token_hash` = :token_hash'
                );
                $delete->execute(['token_hash' => $currentTokenHash]);
                $pdo->commit();
                return null;
            }

            $delete = $pdo->prepare(
                'DELETE FROM `remember_tokens` WHERE `token_hash` = :token_hash'
            );
            $delete->execute(['token_hash' => $currentTokenHash]);

            $insert = $pdo->prepare(
                'INSERT INTO `remember_tokens`
                    (`user_id`, `token_hash`, `expires_at`, `last_used_at`)
                 VALUES
                    (:user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 90 DAY), CURRENT_TIMESTAMP)'
            );
            $insert->execute([
                'user_id' => $userId,
                'token_hash' => $nextTokenHash,
            ]);
            $pdo->commit();

            return (int) $userId;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function revoke(string $tokenHash): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM `remember_tokens` WHERE `token_hash` = :token_hash'
        );
        $statement->execute(['token_hash' => $tokenHash]);
    }
}
