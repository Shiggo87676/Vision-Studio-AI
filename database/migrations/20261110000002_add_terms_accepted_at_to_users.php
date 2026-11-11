<?php

declare(strict_types=1);

return [
    'ALTER TABLE `users`
        ADD COLUMN `terms_accepted_at` TIMESTAMP NULL DEFAULT NULL
        AFTER `email_verified_at`',
];
