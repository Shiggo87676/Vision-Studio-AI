<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDOException;

final class User
{
    public static function create(string $name, string $email, string $passwordHash, bool $termsAccepted): ?array
    {
        if (!$termsAccepted) {
            throw new \InvalidArgumentException('Terms must be accepted before creating an account.');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO `users` (`name`, `email`, `password`, `terms_accepted_at`)
             VALUES (:name, :email, :password, CURRENT_TIMESTAMP)'
        );

        try {
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password' => $passwordHash,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return null;
            }

            throw $exception;
        }

        return [
            'id' => (int) Database::connection()->lastInsertId(),
            'name' => $name,
            'email' => $email,
        ];
    }

    public static function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM `users` WHERE `email` = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn() !== false;
    }

    public static function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT `id`, `name`, `email`, `password`, `status`, `email_verified_at`, `auth_version`
             FROM `users`
             WHERE `email` = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public static function findActiveById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT `id`, `name`, `email`, `status`, `created_at`, `auth_version`
             FROM `users`
             WHERE `id` = :id AND `status` = :status AND `email_verified_at` IS NOT NULL
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'status' => 'active',
        ]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public static function findForPasswordChange(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT `id`, `email`, `password`, `auth_version`
             FROM `users`
             WHERE `id` = :id AND `status` = :status
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'status' => 'active',
        ]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public static function updateName(int $id, string $name): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `users` SET `name` = :name WHERE `id` = :id'
        );
        $statement->execute([
            'name' => $name,
            'id' => $id,
        ]);
    }

    public static function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `users` SET `password` = :password WHERE `id` = :id'
        );
        $statement->execute([
            'password' => $passwordHash,
            'id' => $id,
        ]);
    }
}
