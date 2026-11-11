<header class="site-header" id="top">
    <div class="header-inner">
        <a class="brand" href="/" aria-label="Vision Studio AI home">
            <span class="brand-mark" aria-hidden="true">V</span>
            <span class="brand-name">Vision Studio <span>AI</span></span>
        </a>
        <button class="menu-toggle" type="button" aria-label="Open navigation"
            aria-controls="site-navigation" aria-expanded="false" data-menu-toggle>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>
        <nav class="site-nav" id="site-navigation" aria-label="Main navigation" data-site-nav>
            <div class="nav-links">
                <a href="/#top">Home</a>
                <a href="/#about">About</a>
                <a href="/#features">Features</a>
            </div>
            <?php if ($currentUser !== null): ?>
                <div class="nav-actions">
                    <a class="button button-secondary button-small" href="/dashboard">Dashboard</a>
                    <a class="nav-login" href="/profile">Profile</a>
                    <form action="/logout" method="post">
                        <?= csrf_field() ?>
                        <button class="button button-primary button-small" type="submit">Log out</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="nav-actions">
                    <a class="nav-login" href="/login">Log in</a>
                    <a class="button button-primary button-small" href="/register">Get started <span aria-hidden="true">↗</span></a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
