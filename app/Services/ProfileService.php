<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\User;
use RuntimeException;
use Throwable;

final class ProfileService
{
    public function updateName(int $userId, string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new ValidationException(['name' => 'Enter your full name.']);
        }

        if (strlen($name) > 120 || preg_match('/[\r\n\x00-\x1F\x7F]/', $name) === 1
            || preg_match('/\A.{1,120}\z/us', $name) !== 1) {
            throw new ValidationException(['name' => 'Enter a name between 1 and 120 characters.']);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'SELECT `name` FROM `users`
                 WHERE `id` = :id AND `status` = :status
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'id' => $userId,
                'status' => 'active',
            ]);
            $currentName = $statement->fetchColumn();

            if (!is_string($currentName)) {
                throw new RuntimeException('The active user account could not be found.');
            }

            if ($currentName !== $name) {
                User::updateName($userId, $name);
                (new UserActivityService())->record($userId, 'profile_updated');
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        return $name;
    }
}
