<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Environment;
use RuntimeException;

final class SmtpMailer implements PasswordResetMailer
{
    private const TIMEOUT_SECONDS = 15;

    public function send(string $recipient, string $subject, string $htmlBody, string $textBody): void
    {
        $host = Environment::required('SMTP_HOST');
        $port = Environment::required('SMTP_PORT');
        $encryption = strtolower(Environment::required('SMTP_ENCRYPTION'));
        $username = Environment::get('SMTP_USERNAME') ?? '';
        $password = Environment::get('SMTP_PASSWORD') ?? '';
        $fromAddress = Environment::required('MAIL_FROM_ADDRESS');
        $fromName = Environment::get('MAIL_FROM_NAME') ?? 'Vision Studio AI';

        if (preg_match('/\A[A-Za-z0-9.-]{1,253}\z/D', $host) !== 1
            || filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
            || !in_array($encryption, ['tls', 'ssl'], true)
            || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || preg_match('/[\r\n\x00]/', $username . $password . $fromName . $subject) === 1
            || (($username === '') !== ($password === ''))) {
            throw new RuntimeException('SMTP configuration or message headers are invalid.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client(
            $scheme . $host . ':' . $port,
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('Unable to connect to the configured SMTP server.');
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO vision-studio-ai.local', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);

                if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new RuntimeException('Unable to establish encrypted SMTP transport.');
                }

                $this->command($socket, 'EHLO vision-studio-ai.local', [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $this->writeMessage($socket, $this->message(
                $recipient,
                $fromAddress,
                $fromName,
                $subject,
                $htmlBody,
                $textBody
            ));
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $expectedCodes
     */
    private function command($socket, string $command, array $expectedCodes): void
    {
        $this->writeMessage($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param list<int> $expectedCodes
     */
    private function expect($socket, array $expectedCodes): void
    {
        $responseCode = null;
        do {
            $line = fgets($socket, 516);

            if ($line === false || preg_match('/\A([0-9]{3})([ -])/', $line, $matches) !== 1) {
                throw new RuntimeException('The SMTP server returned an invalid response.');
            }

            $responseCode = (int) $matches[1];
        } while ($matches[2] === '-');

        if (!in_array($responseCode, $expectedCodes, true)) {
            throw new RuntimeException('The SMTP server rejected the email message.');
        }
    }

    /**
     * @param resource $socket
     */
    private function writeMessage($socket, string $message): void
    {
        $length = strlen($message);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($socket, substr($message, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('Unable to write to the SMTP connection.');
            }

            $written += $result;
        }
    }

    private function message(
        string $recipient,
        string $fromAddress,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): string {
        $boundary = 'vsai_' . bin2hex(random_bytes(16));
        $domain = substr(strrchr($fromAddress, '@') ?: 'localhost', 1);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddress . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($textBody))),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($htmlBody))),
            '--' . $boundary . '--',
            '',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }
}
