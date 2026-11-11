<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\User;
use Throwable;

final class PasswordChangeService
{
    private const MAX_ATTEMPTS = 5;

    public function change(
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmation,
        string $ipAddress
    ): int {
        $pdo = Database::connection();
        $lookup = User::findForPasswordChange($userId);

        if ($lookup === null) {
            throw new ValidationException(['current_password' => 'The current password is incorrect.']);
        }

        $limiter = new AuthRateLimiter($pdo, self::MAX_ATTEMPTS);
        $buckets = $limiter->bucketsFor((string) $lookup['email'], $ipAddress, 'password_change');

        if ($limiter->isLimited($buckets)) {
            throw new TooManyPasswordChanges('Too many password change attempts. Try again in 15 minutes.');
        }

        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'SELECT `password`, `auth_version`
                 FROM `users`
                 WHERE `id` = :id AND `status` = :status
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'id' => $userId,
                'status' => 'active',
            ]);
            $user = $statement->fetch();

            if ($user === false || strlen($currentPassword) > 72
                || !password_verify($currentPassword, (string) $user['password'])) {
                $pdo->rollBack();
                $limiter->recordFailure($buckets);
                throw new ValidationException(['current_password' => 'The current password is incorrect.']);
            }

            $errors = [];
            $passwordCharacters = preg_match_all('/./us', $newPassword);

            if ($newPassword === '') {
                $errors['password'] = 'Enter a new password.';
            } elseif ($passwordCharacters === false || $passwordCharacters < 7 || strlen($newPassword) > 72) {
                $errors['password'] = 'Use at least 7 characters and no more than 72 bytes.';
            } elseif (password_verify($newPassword, (string) $user['password'])) {
                $errors['password'] = 'Choose a password different from your current password.';
            }

            if ($confirmation === '') {
                $errors['password_confirmation'] = 'Confirm your new password.';
            } elseif (!hash_equals($newPassword, $confirmation)) {
                $errors['password_confirmation'] = 'The password confirmation does not match.';
            }

            if ($errors !== []) {
                $pdo->rollBack();
                throw new ValidationException($errors);
            }

            $update = $pdo->prepare(
                'UPDATE `users`
                 SET `password` = :password, `auth_version` = `auth_version` + 1
                 WHERE `id` = :id'
            );
            $update->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            $newAuthVersion = (int) $user['auth_version'] + 1;

            $revokeRememberedLogins = $pdo->prepare(
                'DELETE FROM `remember_tokens` WHERE `user_id` = :user_id'
            );
            $revokeRememberedLogins->execute(['user_id' => $userId]);

            $invalidateResetTokens = $pdo->prepare(
                'UPDATE `password_reset_tokens`
                 SET `used_at` = CURRENT_TIMESTAMP
                 WHERE `user_id` = :user_id AND `used_at` IS NULL'
            );
            $invalidateResetTokens->execute(['user_id' => $userId]);

            (new UserActivityService())->record($userId, 'password_changed');
            $pdo->commit();
            return $newAuthVersion;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
