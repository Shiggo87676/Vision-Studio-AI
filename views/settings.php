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
                    <p class="dashboard-breadcrumb">Workspace <span aria-hidden="true">/</span> Settings</p>
                    <p class="dashboard-topbar-caption">Account, profile, and security</p>
                </div>
            </div>
            <div class="dashboard-settings-topbar-user">
                <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $currentUser['name'], 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                <a class="dashboard-settings-user" href="/profile"><?= e((string) $currentUser['name']) ?></a>
            </div>
        </header>
        <div class="dashboard-content settings-content">
            <section class="dashboard-welcome" aria-labelledby="settings-heading">
                <div>
                    <p class="eyebrow">Account preferences</p>
                    <h1 id="settings-heading">Settings</h1>
                    <p>Review your account and manage the settings available today.</p>
                </div>
            </section>
            <section class="settings-section" aria-labelledby="account-information-heading">
                <div class="settings-section-heading">
                    <span class="action-card-icon action-icon-blue" aria-hidden="true">◉</span>
                    <div>
                        <h2 id="account-information-heading">Account information</h2>
                        <p>Your current account details and status.</p>
                    </div>
                </div>
                <dl class="settings-account-details">
                    <div>
                        <dt>Full name</dt>
                        <dd><?= e((string) $currentUser['name']) ?></dd>
                    </div>
                    <div>
                        <dt>Email address</dt>
                        <dd><?= e((string) $currentUser['email']) ?></dd>
                    </div>
                    <div>
                        <dt>Account status</dt>
                        <dd><span class="status-badge"><?= e(ucfirst((string) $currentUser['status'])) ?></span></dd>
                    </div>
                    <div>
                        <dt>Member since</dt>
                        <dd><?= e((new DateTimeImmutable((string) $currentUser['created_at']))->format('F j, Y')) ?></dd>
                    </div>
                </dl>
            </section>
            <section class="settings-card">
                <div>
                    <span class="action-card-icon action-icon-blue" aria-hidden="true">◉</span>
                    <div>
                        <h2>Profile settings</h2>
                        <p>Update your name. Email changes remain unavailable until verification is supported.</p>
                    </div>
                </div>
                <a class="button button-secondary" href="/profile">Edit profile</a>
            </section>
            <section class="settings-card">
                <div>
                    <span class="action-card-icon" aria-hidden="true">⌑</span>
                    <div>
                        <h2>Security settings</h2>
                        <p>Change your password. Other active sessions and remembered logins will be invalidated.</p>
                    </div>
                </div>
                <a class="button button-primary" href="/change-password">Change password</a>
            </section>
            <section class="settings-section settings-preferences" aria-labelledby="preferences-heading">
                <div class="settings-section-heading">
                    <span class="action-card-icon" aria-hidden="true">⚙</span>
                    <div>
                        <h2 id="preferences-heading">Account preferences</h2>
                        <p>No configurable preferences are available yet.</p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
