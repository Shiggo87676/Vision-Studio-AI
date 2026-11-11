<?php

declare(strict_types=1);

return [
    'CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `used_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
        KEY `idx_password_reset_tokens_user_expires` (`user_id`, `expires_at`),
        CONSTRAINT `fk_password_reset_tokens_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
