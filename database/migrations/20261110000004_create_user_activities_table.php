<?php

declare(strict_types=1);

return [
    'CREATE TABLE IF NOT EXISTS `user_activities` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `activity_type` VARCHAR(50) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `is_notification` TINYINT(1) NOT NULL DEFAULT 0,
        `read_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_activities_recent` (`user_id`, `created_at`, `id`),
        KEY `idx_user_activities_notifications` (`user_id`, `is_notification`, `read_at`, `created_at`),
        CONSTRAINT `fk_user_activities_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
