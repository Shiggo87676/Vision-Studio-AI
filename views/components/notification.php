<div class="notification notification-<?= e($notificationType ?? 'info') ?>" role="<?= ($notificationType ?? 'info') === 'error' ? 'alert' : 'status' ?>">
    <span class="notification-icon" aria-hidden="true"><?= ($notificationType ?? 'info') === 'error' ? '!' : (($notificationType ?? 'info') === 'success' ? '✓' : 'i') ?></span>
    <p><?= e($notificationMessage) ?></p>
</div>
