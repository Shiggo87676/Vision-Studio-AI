<?php

declare(strict_types=1);

return [
    'CREATE TABLE IF NOT EXISTS `users` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(120) NOT NULL,
        `email` VARCHAR(254) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT \'active\',
        `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_email` (`email`),
        KEY `idx_users_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
