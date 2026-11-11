<?php

declare(strict_types=1);

return [
    'ALTER TABLE `users`
        ADD COLUMN `auth_version` INT UNSIGNED NOT NULL DEFAULT 1
        AFTER `status`',
];
