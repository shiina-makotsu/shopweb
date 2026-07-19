export const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const flashStatus = (message) => {
    document.querySelector('[data-cart-toast]')?.remove();

    const toast = document.createElement('div');
    toast.dataset.cartToast = 'true';
    toast.className = 'shop-cart-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    window.setTimeout(() => toast.remove(), 1800);
};

export const setupMobileMenu = () => {
    const menu = document.querySelector('[data-mobile-menu]');
    const openButtons = document.querySelectorAll('[data-mobile-menu-open]');
    const closeButtons = document.querySelectorAll('[data-mobile-menu-close]');

    if (!menu || openButtons.length === 0) return;

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
        openButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
    };

    openButtons.forEach((button) => button.addEventListener('click', () => setOpen(true)));
    closeButtons.forEach((button) => button.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
};

export const setupFloatingCart = () => {
    const panel = document.querySelector('[data-floating-cart-panel]');
    const toggle = document.querySelector('[data-floating-cart-toggle]');
    const close = document.querySelector('[data-floating-cart-close]');

    if (!panel || !toggle) return;

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
    close?.addEventListener('click', () => setOpen(false));
    document.addEventListener('click', (event) => {
        if (!panel.classList.contains('hidden') && !panel.contains(event.target) && !toggle.contains(event.target)) {
            setOpen(false);
        }
    });
};

export const setupRegistrationOnboarding = () => {
    const onboarding = document.querySelector('[data-registration-onboarding]');
    const close = document.querySelector('[data-registration-onboarding-close]');

    if (onboarding && close) close.addEventListener('click', () => onboarding.remove());
};
