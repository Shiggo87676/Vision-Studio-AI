<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AuthRateLimiter
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $maxAttempts = self::MAX_ATTEMPTS
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 255) {
            throw new \InvalidArgumentException('Rate limit attempts must be between 1 and 255.');
        }
    }

    /**
     * @param list<string> $bucketHashes
     */
    public function isLimited(array $bucketHashes): bool
    {
        return $this->withLockedBuckets($bucketHashes, function (PDO $pdo, array $keys): bool {
            $this->resetExpiredWindows($pdo, $keys);
            $statement = $pdo->prepare(
                'SELECT `locked_until` IS NOT NULL AND `locked_until` > CURRENT_TIMESTAMP
                 FROM `auth_login_rate_limits`
                 WHERE `bucket_hash` = :bucket_hash'
            );

            foreach ($keys as $key) {
                $statement->execute(['bucket_hash' => $key]);

                if ((int) $statement->fetchColumn() === 1) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param list<string> $bucketHashes
     */
    public function recordFailure(array $bucketHashes): void
    {
        $this->withLockedBuckets($bucketHashes, function (PDO $pdo, array $keys): void {
            $this->resetExpiredWindows($pdo, $keys);
            $statement = $pdo->prepare(
                'UPDATE `auth_login_rate_limits`
                 SET `locked_until` = CASE
                         WHEN `attempts` + 1 >= :max_attempts
                         THEN DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
                         ELSE `locked_until`
                     END,
                     `attempts` = `attempts` + 1
                 WHERE `bucket_hash` = :bucket_hash'
            );

            foreach ($keys as $key) {
                $statement->execute([
                    'max_attempts' => $this->maxAttempts,
                    'bucket_hash' => $key,
                ]);
            }
        });
    }

    public function clearEmailBucket(string $email): void
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM `auth_login_rate_limits` WHERE `bucket_hash` = :bucket_hash'
        );
        $statement->execute(['bucket_hash' => $this->emailBucket($email)]);
    }

    /**
     * @return list<string>
     */
    public function bucketsFor(string $email, string $ipAddress, string $scope = 'login'): array
    {
        if (preg_match('/\A[a-z0-9_-]{1,32}\z/D', $scope) !== 1) {
            throw new \InvalidArgumentException('Rate limit scope is invalid.');
        }

        $email = substr(strtolower(trim($email)), 0, 254);
        $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) === false ? 'unknown' : $ipAddress;
        $emailPrefix = $scope === 'login' ? 'email:' : 'email:' . $scope . ':';
        $ipPrefix = $scope === 'login' ? 'ip:' : 'ip:' . $scope . ':';
        $buckets = [
            hash('sha256', $emailPrefix . $email),
            hash('sha256', $ipPrefix . $ipAddress),
        ];
        sort($buckets, SORT_STRING);

        return array_values(array_unique($buckets));
    }

    private function emailBucket(string $email): string
    {
        return hash('sha256', 'email:' . $email);
    }

    /**
     * @param list<string> $bucketHashes
     * @param callable(PDO, list<string>): mixed $callback
     */
    private function withLockedBuckets(array $bucketHashes, callable $callback): mixed
    {
        $pdo = $this->connection();
        $keys = array_values(array_unique($bucketHashes));
        sort($keys, SORT_STRING);
        $pdo->exec(
            'DELETE FROM `auth_login_rate_limits`
             WHERE `updated_at` < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)'
        );
        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO `auth_login_rate_limits` (`bucket_hash`) VALUES (:bucket_hash)'
            );
            $lock = $pdo->prepare(
                'SELECT `bucket_hash`
                 FROM `auth_login_rate_limits`
                 WHERE `bucket_hash` = :bucket_hash
                 FOR UPDATE'
            );

            foreach ($keys as $key) {
                $insert->execute(['bucket_hash' => $key]);
                $lock->execute(['bucket_hash' => $key]);
                $lock->fetchColumn();
            }

            $result = $callback($pdo, $keys);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param list<string> $keys
     */
    private function resetExpiredWindows(PDO $pdo, array $keys): void
    {
        $statement = $pdo->prepare(
            'UPDATE `auth_login_rate_limits`
             SET `attempts` = 0,
                 `window_started_at` = CURRENT_TIMESTAMP,
                 `locked_until` = NULL
             WHERE `bucket_hash` = :bucket_hash
               AND `window_started_at` <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
               AND (`locked_until` IS NULL OR `locked_until` <= CURRENT_TIMESTAMP)'
        );

        foreach ($keys as $key) {
            $statement->execute(['bucket_hash' => $key]);
        }
    }

    private function connection(): PDO
    {
        return $this->pdo;
    }
}
