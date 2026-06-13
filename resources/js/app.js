import './bootstrap';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const flashCartStatus = (message) => {
    const existing = document.querySelector('[data-cart-toast]');
    existing?.remove();

    const toast = document.createElement('div');
    toast.dataset.cartToast = 'true';
    toast.className = 'shop-cart-toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    window.setTimeout(() => toast.remove(), 1800);
};

const animateToCart = (source) => {
    const target = document.querySelector('#site-cart-target');

    if (!target || prefersReducedMotion()) {
        return;
    }

    const sourceRect = source.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();
    const dot = document.createElement('span');

    dot.className = 'shop-cart-fly-dot';
    dot.style.left = `${sourceRect.left + sourceRect.width / 2}px`;
    dot.style.top = `${sourceRect.top + sourceRect.height / 2}px`;
    dot.style.setProperty('--cart-fly-x', `${targetRect.left + targetRect.width / 2 - sourceRect.left - sourceRect.width / 2}px`);
    dot.style.setProperty('--cart-fly-y', `${targetRect.top + targetRect.height / 2 - sourceRect.top - sourceRect.height / 2}px`);

    document.body.appendChild(dot);
    dot.addEventListener('animationend', () => dot.remove(), { once: true });
};

const refreshCartSummary = (payload) => {
    if (payload.cart_count !== undefined) {
        document.querySelectorAll('[data-cart-count]').forEach((node) => {
            node.textContent = payload.cart_count;
        });
    }

    if (payload.cart_subtotal !== undefined) {
        document.querySelectorAll('[data-cart-subtotal]').forEach((node) => {
            node.textContent = payload.cart_subtotal;
        });
    }

    const target = document.querySelector('#site-cart-target');

    if (target && payload.cart_count !== undefined) {
        target.setAttribute('aria-label', `购物车 ${payload.cart_count} 件`);
    }
};

const setupMobileMenu = () => {
    const menu = document.querySelector('[data-mobile-menu]');
    const openButtons = document.querySelectorAll('[data-mobile-menu-open]');
    const closeButtons = document.querySelectorAll('[data-mobile-menu-close]');

    if (!menu || openButtons.length === 0) {
        return;
    }

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
        openButtons.forEach((button) => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
    };

    openButtons.forEach((button) => button.addEventListener('click', () => setOpen(true)));
    closeButtons.forEach((button) => button.addEventListener('click', () => setOpen(false)));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
};

setupMobileMenu();

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-cart-add-form]')) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');
    button?.setAttribute('disabled', 'disabled');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('add-to-cart-failed');
        }

        const payload = await response.json();

        refreshCartSummary(payload);
        animateToCart(button ?? form);
        flashCartStatus(payload.message ?? '已加入购物车。');
    } catch (error) {
        form.submit();
    } finally {
        button?.removeAttribute('disabled');
    }
});
