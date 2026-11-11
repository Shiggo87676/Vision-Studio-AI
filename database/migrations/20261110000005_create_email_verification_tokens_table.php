<?php

declare(strict_types=1);

return [
    'CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `used_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_email_verification_tokens_hash` (`token_hash`),
        KEY `idx_email_verification_tokens_user` (`user_id`, `used_at`, `expires_at`),
        CONSTRAINT `fk_email_verification_tokens_user`
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
