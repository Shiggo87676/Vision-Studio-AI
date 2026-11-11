<?php
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $letters !== '' ? $letters : 'V';
};
$displayName = (string) $currentUser['name'];
$firstName = preg_split('/\s+/u', trim($displayName), 2)[0] ?? $displayName;
?>
<div class="dashboard-shell">
    <button class="dashboard-sidebar-backdrop" type="button" aria-label="Close dashboard navigation"
        data-dashboard-sidebar-close></button>
    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div class="dashboard-topbar-start">
                <button class="dashboard-menu-toggle" type="button" aria-label="Open dashboard navigation"
                    aria-controls="dashboard-sidebar" aria-expanded="false" data-dashboard-sidebar-toggle>
                    <span aria-hidden="true">☰</span>
                </button>
                <div>
                    <p class="dashboard-breadcrumb">Workspace <span aria-hidden="true">/</span> Dashboard</p>
                    <p class="dashboard-topbar-caption">Your creative space</p>
                </div>
            </div>
            <div class="dashboard-topbar-actions">
                <details class="dashboard-notifications">
                    <summary aria-label="Notifications, <?= (int) $unreadNotificationCount ?> unread">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                            <path d="M10 21h4" />
                        </svg>
                        <?php if ($unreadNotificationCount > 0): ?>
                            <span class="notification-count"><?= (int) $unreadNotificationCount ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="dashboard-popover">
                        <div class="notification-popover-heading">
                            <strong>Notifications</strong>
                            <?php if ($unreadNotificationCount > 0): ?>
                                <span><?= (int) $unreadNotificationCount ?> new</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($notifications === []): ?>
                            <p>No notifications yet. Important account updates will appear here.</p>
                        <?php else: ?>
                            <ul class="notification-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <span><?= e((string) $notification['description']) ?></span>
                                        <time datetime="<?= e((string) $notification['created_at']) ?>">
                                            <?= e((new DateTimeImmutable((string) $notification['created_at']))->format('M j, Y')) ?>
                                        </time>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($unreadNotificationCount > 0): ?>
                                <form action="/notifications/read" method="post" class="notification-read-form">
                                    <?= csrf_field() ?>
                                    <button type="submit">Mark all as read</button>
                                </form>
                            <?php else: ?>
                                <p class="notification-read-message">You’re all caught up.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </details>
                <details class="dashboard-user-menu">
                    <summary>
                        <span class="user-avatar" aria-hidden="true"><?= e($initials($displayName)) ?></span>
                        <span class="dashboard-user-name"><?= e($displayName) ?></span>
                        <span class="dropdown-chevron" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="dashboard-popover user-popover">
                        <span class="popover-email"><?= e((string) $currentUser['email']) ?></span>
                        <a href="/profile">Profile</a>
                        <a href="/settings">Settings</a>
                    </div>
                </details>
            </div>
        </header>

        <div class="dashboard-content">
            <section class="dashboard-welcome" aria-labelledby="dashboard-heading">
                <div>
                    <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Your workspace</p>
                    <h1 id="dashboard-heading">Welcome back, <?= e($firstName) ?>.</h1>
                    <p>Your studio is ready whenever inspiration strikes.</p>
                </div>
                <a class="button button-primary dashboard-create-button" href="#ai-studio">
                    <span aria-hidden="true">✦</span> Explore AI Studio
                </a>
            </section>

            <section class="dashboard-stats" aria-label="Account overview">
                <article class="dashboard-stat-card">
                    <span class="dashboard-stat-icon stat-purple" aria-hidden="true">▧</span>
                    <p class="dashboard-stat-label">Projects</p>
                    <strong class="dashboard-stat-value">Not available</strong>
                    <span class="dashboard-stat-note">Project tracking isn’t enabled yet</span>
                </article>
                <article class="dashboard-stat-card">
                    <span class="dashboard-stat-icon stat-blue" aria-hidden="true">◷</span>
                    <p class="dashboard-stat-label">Recent activity</p>
                    <strong class="dashboard-stat-value"><?= (int) $activityCount ?></strong>
                    <span class="dashboard-stat-note">Recorded account events</span>
                </article>
                <article class="dashboard-stat-card">
                    <span class="dashboard-stat-icon stat-peach" aria-hidden="true">▤</span>
                    <p class="dashboard-stat-label">Member since</p>
                    <strong class="dashboard-stat-value"><?= e((new DateTimeImmutable((string) $currentUser['created_at']))->format('M Y')) ?></strong>
                    <span class="dashboard-stat-note"><?= e((new DateTimeImmutable((string) $currentUser['created_at']))->format('F j, Y')) ?></span>
                </article>
                <article class="dashboard-stat-card">
                    <span class="dashboard-stat-icon stat-mint" aria-hidden="true">✓</span>
                    <p class="dashboard-stat-label">Account status</p>
                    <strong class="dashboard-stat-value"><span class="status-badge"><?= e(ucfirst((string) $currentUser['status'])) ?></span></strong>
                    <span class="dashboard-stat-note">Status from your account</span>
                </article>
            </section>

            <section class="dashboard-quick-actions" aria-label="Quick actions">
                <a class="dashboard-action-card" href="#ai-studio">
                    <span class="action-card-icon" aria-hidden="true">✦</span>
                    <span><strong>Open AI Studio</strong><small>Explore the creative workspace</small></span>
                    <span class="action-arrow" aria-hidden="true">↗</span>
                </a>
                <a class="dashboard-action-card" href="/profile">
                    <span class="action-card-icon action-icon-blue" aria-hidden="true">◉</span>
                    <span><strong>Manage your profile</strong><small>Update your account details</small></span>
                    <span class="action-arrow" aria-hidden="true">↗</span>
                </a>
            </section>

            <div class="dashboard-panels">
                <section class="dashboard-panel" id="projects" aria-labelledby="projects-heading">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="eyebrow">Your work</p>
                            <h2 id="projects-heading">Recent projects</h2>
                        </div>
                        <a href="#ai-studio" class="dashboard-panel-link">My Projects <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="dashboard-empty-state">
                        <span class="empty-state-icon" aria-hidden="true">▧</span>
                        <h3>No projects yet</h3>
                        <p>Projects will appear here when project creation is available.</p>
                        <a href="#ai-studio" class="text-link">Explore AI Studio <span aria-hidden="true">↗</span></a>
                    </div>
                </section>

                <section class="dashboard-panel activity-panel" id="activity" aria-labelledby="activity-heading">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="eyebrow">Your workspace</p>
                            <h2 id="activity-heading">Recent activity</h2>
                        </div>
                        <span class="dashboard-panel-muted">Activity feed</span>
                    </div>
                    <?php if ($activities === []): ?>
                        <div class="dashboard-empty-state">
                            <span class="empty-state-icon empty-icon-blue" aria-hidden="true">◷</span>
                            <h3>No activity to show</h3>
                            <p>Important account activity will appear here as it happens.</p>
                        </div>
                    <?php else: ?>
                        <ul class="activity-list">
                            <?php foreach ($activities as $activity): ?>
                                <li>
                                    <span class="activity-marker" aria-hidden="true"></span>
                                    <span class="activity-description"><?= e((string) $activity['description']) ?></span>
                                    <time datetime="<?= e((string) $activity['created_at']) ?>">
                                        <?= e((new DateTimeImmutable((string) $activity['created_at']))->format('M j, Y · g:i a')) ?>
                                    </time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <section class="dashboard-studio-callout" id="ai-studio" aria-labelledby="studio-heading">
                <div class="studio-callout-icon" aria-hidden="true">✦</div>
                <div>
                    <p class="eyebrow">Coming into focus</p>
                    <h2 id="studio-heading">Your AI Studio is taking shape</h2>
                    <p>Creative tools and project creation aren’t available yet. We’ll make this space ready when they are.</p>
                </div>
                <span class="coming-soon-badge">Coming soon</span>
            </section>
        </div>
    </main>
</div>
