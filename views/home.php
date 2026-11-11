<main>
    <?php if ($successMessage !== ''): ?>
        <div class="home-notice">
            <?php
            $notificationType = 'success';
            $notificationMessage = $successMessage;
            require BASE_PATH . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'notification.php';
            ?>
        </div>
    <?php endif; ?>
    <section class="hero-section" aria-labelledby="hero-heading">
        <div class="hero-copy">
            <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> A new canvas for your imagination</p>
            <h1 id="hero-heading">Your ideas deserve to be <span>seen differently.</span></h1>
            <p class="hero-description">
                Meet a more intuitive creative space. Turn the things you imagine into visuals,
                explore new directions, and bring your next big idea into focus.
            </p>
            <div class="hero-actions">
                <?php if ($currentUser === null): ?>
                    <a class="button button-primary button-large" href="/register">
                        Get started <span aria-hidden="true">↗</span>
                    </a>
                    <a class="button button-secondary button-large" href="#features">Explore the studio</a>
                <?php else: ?>
                    <a class="button button-primary button-large" href="/dashboard">
                        Continue to your dashboard <span aria-hidden="true">↗</span>
                    </a>
                    <a class="button button-secondary button-large" href="#features">Explore the studio</a>
                <?php endif; ?>
            </div>
            <p class="hero-note">A space to think bigger, make freely, and find your next perspective.</p>
        </div>
        <div class="hero-art" aria-label="Abstract preview of a creative AI studio">
            <div class="art-glow" aria-hidden="true"></div>
            <div class="art-orbit art-orbit-one" aria-hidden="true"></div>
            <div class="art-orbit art-orbit-two" aria-hidden="true"></div>
            <div class="art-window">
                <div class="art-window-bar">
                    <span class="window-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span>Untitled / 01</span>
                    <span class="window-status"><i aria-hidden="true"></i> Studio</span>
                </div>
                <div class="art-canvas">
                    <div class="canvas-sun" aria-hidden="true"></div>
                    <div class="canvas-mountain canvas-mountain-back" aria-hidden="true"></div>
                    <div class="canvas-mountain canvas-mountain-front" aria-hidden="true"></div>
                    <div class="canvas-shimmer" aria-hidden="true"></div>
                    <span class="canvas-caption">A different point of view</span>
                </div>
                <div class="art-prompt">
                    <span class="prompt-spark" aria-hidden="true">✦</span>
                    <span>Imagine something unexpected...</span>
                    <span class="prompt-arrow" aria-hidden="true">↑</span>
                </div>
            </div>
            <div class="art-float art-float-top"><span aria-hidden="true">✦</span> A spark of an idea</div>
            <div class="art-float art-float-bottom"><span aria-hidden="true">◌</span> Make it your own</div>
        </div>
        <a class="scroll-cue" href="#features"><span aria-hidden="true"></span> Scroll to explore</a>
    </section>

    <section class="section features-section" id="features" aria-labelledby="features-heading">
        <div class="section-heading">
            <p class="eyebrow">A studio without borders</p>
            <h2 id="features-heading">Start with a thought.<br><span>See where it takes you.</span></h2>
            <p>Every great creation begins somewhere. Vision Studio AI gives your ideas room to grow into something entirely your own.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card feature-card-purple">
                <span class="feature-icon" aria-hidden="true">✳</span>
                <p class="feature-index">01 / Imagine</p>
                <h3>From words to worlds</h3>
                <p>Give shape to the scene in your mind. Explore visual ideas from a simple description and discover possibilities you hadn't considered.</p>
                <span class="feature-decoration feature-decoration-orbit" aria-hidden="true"></span>
            </article>
            <article class="feature-card feature-card-blue">
                <span class="feature-icon" aria-hidden="true">◈</span>
                <p class="feature-index">02 / Explore</p>
                <h3>Find your own point of view</h3>
                <p>Follow a new creative direction, experiment with a different mood, and make space for the happy accidents that move an idea forward.</p>
                <span class="feature-decoration feature-decoration-grid" aria-hidden="true"></span>
            </article>
            <article class="feature-card feature-card-peach">
                <span class="feature-icon" aria-hidden="true">⌁</span>
                <p class="feature-index">03 / Create</p>
                <h3>Make the idea yours</h3>
                <p>Refine your vision with a thoughtful creative workflow that keeps you in control, from the first spark to the details that matter.</p>
                <span class="feature-decoration feature-decoration-wave" aria-hidden="true"></span>
            </article>
        </div>
    </section>

    <section class="about-section" id="about" aria-labelledby="about-heading">
        <div class="about-visual" aria-hidden="true">
            <div class="about-shape about-shape-one"></div>
            <div class="about-shape about-shape-two"></div>
            <div class="about-shape about-shape-three"></div>
            <div class="about-stamp"><span>MAKE</span><span>ROOM</span><span>TO IMAGINE</span></div>
        </div>
        <div class="about-copy">
            <p class="eyebrow">A little more possibility</p>
            <h2 id="about-heading">Creativity isn't a destination. It's a way of seeing.</h2>
            <p>Vision Studio AI is being built for curious minds, bold beginnings, and all the ideas that don't fit neatly inside the lines.</p>
            <p>We believe technology should open doors, not define what you make. Your instincts lead. The tools follow.</p>
            <a class="text-link" href="/register">Find your creative flow <span aria-hidden="true">↗</span></a>
        </div>
    </section>

    <section class="closing-cta" aria-labelledby="closing-heading">
        <div class="closing-orb closing-orb-one" aria-hidden="true"></div>
        <div class="closing-orb closing-orb-two" aria-hidden="true"></div>
        <p class="eyebrow">Your next idea is waiting</p>
        <h2 id="closing-heading">What will you imagine?</h2>
        <p>Start with a blank canvas. Leave with a new perspective.</p>
        <a class="button button-light button-large" href="/register">Get started <span aria-hidden="true">↗</span></a>
    </section>
</main>
