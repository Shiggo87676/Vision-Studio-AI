<?php

declare(strict_types=1);

return [
    'CREATE TABLE IF NOT EXISTS `auth_login_rate_limits` (
        `bucket_hash` CHAR(64) NOT NULL,
        `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `window_started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `locked_until` TIMESTAMP NULL DEFAULT NULL,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`bucket_hash`),
        KEY `idx_auth_login_rate_limits_updated_at` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
