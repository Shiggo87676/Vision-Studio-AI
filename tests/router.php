<?php

declare(strict_types=1);

$_SERVER['REMOTE_ADDR'] = '203.0.113.47';
putenv('SMTP_HOST=');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'router.php';
