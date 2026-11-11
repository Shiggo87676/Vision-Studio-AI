<?php

declare(strict_types=1);

namespace App\Services;

interface PasswordResetMailer
{
    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void;
}
