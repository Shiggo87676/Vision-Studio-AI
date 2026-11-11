<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use PDO;

final class UserActivityService
{
    /**
     * @var array<string, array{description: string, notification: bool}>
     */
    private const EVENTS = [
        'account_registered' => [
            'description' => 'Your Vision Studio AI account was created.',
            'notification' => true,
        ],
        'email_verified' => [
            'description' => 'Your email address was verified.',
            'notification' => true,
        ],
        'password_reset_requested' => [
            'description' => 'A password reset link was requested for your account.',
            'notification' => true,
        ],
        'password_reset' => [
            'description' => 'Your password was reset.',
            'notification' => true,
        ],
        'password_changed' => [
            'description' => 'Your password was changed.',
            'notification' => true,
        ],
        'profile_updated' => [
            'description' => 'Your profile name was updated.',
            'notification' => true,
        ],
    ];

    public function record(int $userId, string $activityType): void
    {
        $event = self::EVENTS[$activityType] ?? null;

        if ($event === null) {
            throw new \InvalidArgumentException('The activity type is not supported.');
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO `user_activities`
                (`user_id`, `activity_type`, `description`, `is_notification`)
             VALUES (:user_id, :activity_type, :description, :is_notification)'
        );
        $statement->execute([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'description' => $event['description'],
            'is_notification' => $event['notification'] ? 1 : 0,
        ]);
    }

    /**
     * @return list<array{id: int, activity_type: string, description: string, created_at: string}>
     */
    public function recentForUser(int $userId, int $limit = 6): array
    {
        $limit = max(1, min($limit, 20));
        $statement = Database::connection()->prepare(
            'SELECT `id`, `activity_type`, `description`, `created_at`
             FROM `user_activities`
             WHERE `user_id` = :user_id
             ORDER BY `created_at` DESC, `id` DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{id: int, activity_type: string, description: string, created_at: string}>
     */
    public function notificationsForUser(int $userId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $statement = Database::connection()->prepare(
            'SELECT `id`, `activity_type`, `description`, `created_at`
             FROM `user_activities`
             WHERE `user_id` = :user_id AND `is_notification` = 1
             ORDER BY `created_at` DESC, `id` DESC
             LIMIT ' . $limit
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function unreadNotificationCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM `user_activities`
             WHERE `user_id` = :user_id
               AND `is_notification` = 1
               AND `read_at` IS NULL'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function activityCountForUser(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM `user_activities` WHERE `user_id` = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function markNotificationsRead(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `user_activities`
             SET `read_at` = CURRENT_TIMESTAMP
             WHERE `user_id` = :user_id
               AND `is_notification` = 1
               AND `read_at` IS NULL'
        );
        $statement->execute(['user_id' => $userId]);
    }
}
