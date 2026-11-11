<aside class="dashboard-sidebar" id="dashboard-sidebar" data-dashboard-sidebar>
    <a class="dashboard-brand" href="/dashboard" aria-label="Vision Studio AI dashboard">
        <span class="brand-mark" aria-hidden="true">V</span>
        <span class="brand-name">Vision Studio <span>AI</span></span>
    </a>
    <p class="sidebar-section-label">Workspace</p>
    <nav class="dashboard-nav" aria-label="Dashboard navigation">
        <a class="dashboard-nav-link<?= $activePage === 'dashboard' ? ' active' : '' ?>" href="/dashboard"
            <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
            <span class="dashboard-nav-icon" aria-hidden="true">▦</span> Dashboard
        </a>
        <a class="dashboard-nav-link" href="/dashboard#ai-studio">
            <span class="dashboard-nav-icon" aria-hidden="true">✦</span> AI Studio
            <span class="sidebar-coming-soon">Soon</span>
        </a>
        <a class="dashboard-nav-link" href="/dashboard#projects">
            <span class="dashboard-nav-icon" aria-hidden="true">▧</span> My Projects
            <span class="sidebar-coming-soon">Soon</span>
        </a>
        <a class="dashboard-nav-link" href="/dashboard#activity">
            <span class="dashboard-nav-icon" aria-hidden="true">◷</span> Recent Activity
        </a>
    </nav>
    <p class="sidebar-section-label sidebar-account-label">Account</p>
    <nav class="dashboard-nav" aria-label="Account navigation">
        <a class="dashboard-nav-link" href="/profile">
            <span class="dashboard-nav-icon" aria-hidden="true">◉</span> Profile
        </a>
        <a class="dashboard-nav-link<?= $activePage === 'settings' ? ' active' : '' ?>" href="/settings"
            <?= $activePage === 'settings' ? 'aria-current="page"' : '' ?>>
            <span class="dashboard-nav-icon" aria-hidden="true">⚙</span> Settings
        </a>
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-help-card">
            <span class="help-spark" aria-hidden="true">✦</span>
            <strong>Room to imagine</strong>
            <p>Your creative space is ready to grow.</p>
        </div>
        <form action="/logout" method="post" class="dashboard-logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="dashboard-nav-link dashboard-logout">
                <span class="dashboard-nav-icon" aria-hidden="true">↪</span> Log out
            </button>
        </form>
        <p class="sidebar-copyright">&copy; <?= date('Y') ?> Vision Studio AI</p>
    </div>
</aside>
