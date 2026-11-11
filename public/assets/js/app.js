const menuToggle = document.querySelector('[data-menu-toggle]');
const siteNavigation = document.querySelector('[data-site-nav]');

if (menuToggle instanceof HTMLButtonElement && siteNavigation instanceof HTMLElement) {
    const closeNavigation = () => {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open navigation');
        siteNavigation.dataset.open = 'false';
    };

    menuToggle.addEventListener('click', () => {
        const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
        menuToggle.setAttribute('aria-label', isOpen ? 'Open navigation' : 'Close navigation');
        siteNavigation.dataset.open = String(!isOpen);
    });

    siteNavigation.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a')) {
            closeNavigation();
        }
    });

    document.addEventListener('click', (event) => {
        if (
            siteNavigation.dataset.open === 'true'
            && event.target instanceof Node
            && !siteNavigation.contains(event.target)
            && !menuToggle.contains(event.target)
        ) {
            closeNavigation();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && siteNavigation.dataset.open === 'true') {
            closeNavigation();
            menuToggle.focus();
        }
    });
}

const dashboardSidebarToggle = document.querySelector('[data-dashboard-sidebar-toggle]');
const dashboardSidebar = document.querySelector('[data-dashboard-sidebar]');
const dashboardSidebarClose = document.querySelector('[data-dashboard-sidebar-close]');

if (
    dashboardSidebarToggle instanceof HTMLButtonElement
    && dashboardSidebar instanceof HTMLElement
    && dashboardSidebarClose instanceof HTMLButtonElement
) {
    const dashboardMobileMedia = window.matchMedia('(max-width: 800px)');

    const closeDashboardSidebar = () => {
        dashboardSidebar.dataset.open = 'false';
        dashboardSidebarClose.dataset.open = 'false';
        dashboardSidebarToggle.setAttribute('aria-expanded', 'false');
        dashboardSidebarToggle.setAttribute('aria-label', 'Open dashboard navigation');
        dashboardSidebar.inert = dashboardMobileMedia.matches;
    };

    const openDashboardSidebar = () => {
        dashboardSidebar.dataset.open = 'true';
        dashboardSidebarClose.dataset.open = 'true';
        dashboardSidebarToggle.setAttribute('aria-expanded', 'true');
        dashboardSidebarToggle.setAttribute('aria-label', 'Close dashboard navigation');
        dashboardSidebar.inert = false;
    };

    closeDashboardSidebar();
    dashboardMobileMedia.addEventListener('change', closeDashboardSidebar);

    dashboardSidebarToggle.addEventListener('click', () => {
        const isOpen = dashboardSidebar.dataset.open === 'true';
        if (isOpen) {
            closeDashboardSidebar();
        } else {
            openDashboardSidebar();
        }
    });

    dashboardSidebarClose.addEventListener('click', closeDashboardSidebar);
    dashboardSidebar.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a')) {
            closeDashboardSidebar();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dashboardSidebar.dataset.open === 'true') {
            closeDashboardSidebar();
            dashboardSidebarToggle.focus();
        }
    });
}

document.querySelectorAll('.auth-form [data-password-toggle]').forEach((toggle) => {
    if (!(toggle instanceof HTMLButtonElement)) {
        return;
    }

    const inputId = toggle.dataset.passwordToggle;
    const passwordInput = inputId ? document.getElementById(inputId) : null;

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    toggle.addEventListener('click', () => {
        const isVisible = passwordInput.type === 'text';
        passwordInput.type = isVisible ? 'password' : 'text';
        toggle.textContent = isVisible ? 'Show' : 'Hide';
        toggle.setAttribute('aria-pressed', String(!isVisible));
        toggle.setAttribute(
            'aria-label',
            `${isVisible ? 'Show' : 'Hide'} ${passwordInput.id === 'password' ? 'password' : 'confirmation password'}`
        );
    });
});

const registrationForm = document.querySelector('.auth-form[action="/register"]');

document.querySelectorAll('.auth-form[data-submit-loading]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitButton = form.querySelector('button[type="submit"]');

    if (submitButton instanceof HTMLButtonElement) {
        const defaultLabel = submitButton.textContent.trim();
        const loadingLabel = submitButton.dataset.loadingLabel ?? 'Sending...';

        form.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.dataset.loading = 'true';
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.textContent = loadingLabel;
            submitButton.dataset.defaultLabel = defaultLabel;
        });
    }
});

if (registrationForm instanceof HTMLFormElement) {
    const submitButton = registrationForm.querySelector('button[type="submit"]');

    if (submitButton instanceof HTMLButtonElement) {
        const defaultLabel = submitButton.textContent.trim();
        const loadingLabel = submitButton.dataset.loadingLabel ?? 'Creating your account...';

        registrationForm.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.dataset.loading = 'true';
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.textContent = loadingLabel;
            submitButton.dataset.defaultLabel = defaultLabel;
        });
    }

    const passwordInput = registrationForm.querySelector('#password');
    const confirmationInput = registrationForm.querySelector('#password_confirmation');
    const minimumRule = registrationForm.querySelector('[data-password-rule="minimum"]');
    const matchMessage = registrationForm.querySelector('#password-match');

    if (
        passwordInput instanceof HTMLInputElement
        && confirmationInput instanceof HTMLInputElement
        && minimumRule instanceof HTMLLIElement
        && matchMessage instanceof HTMLParagraphElement
    ) {
        const updateRule = (rule, isMet, hasPassword) => {
            rule.dataset.state = !hasPassword ? 'waiting' : isMet ? 'met' : 'unmet';
            rule.querySelector('.rule-status').textContent = !hasPassword
                ? ''
                : isMet
                    ? ''
                    : 'Not met';
        };

        const updatePasswordFeedback = () => {
            const password = passwordInput.value;
            const hasPassword = password.length > 0;
            const characterCount = Array.from(password).length;
            const byteCount = new TextEncoder().encode(password).length;
            const meetsMinimum = characterCount >= 7;
            const meetsMaximum = byteCount <= 72;

            updateRule(minimumRule, meetsMinimum, hasPassword);
            passwordInput.setAttribute(
                'aria-invalid',
                hasPassword && (!meetsMinimum || !meetsMaximum) ? 'true' : 'false'
            );

            const confirmation = confirmationInput.value;
            const hasConfirmation = confirmation.length > 0;

            if (!hasPassword || !hasConfirmation) {
                matchMessage.dataset.state = 'waiting';
                matchMessage.textContent = hasPassword
                    ? 'Re-enter your password to confirm it.'
                    : 'Enter a password to check the match.';
                confirmationInput.setAttribute('aria-invalid', 'false');
                return;
            }

            const matches = password === confirmation;
            matchMessage.dataset.state = matches ? 'met' : 'unmet';
            matchMessage.textContent = matches ? 'Passwords match.' : 'Passwords do not match.';
            confirmationInput.setAttribute(
                'aria-invalid',
                matches ? 'false' : 'true'
            );
        };

        passwordInput.addEventListener('input', updatePasswordFeedback);
        confirmationInput.addEventListener('input', updatePasswordFeedback);
        updatePasswordFeedback();
    }
}