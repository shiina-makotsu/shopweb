import { csrfToken, fetchJson } from '../core/http';
import { flashStatus, prefersReducedMotion } from './ui';

const animateToCart = (source) => {
    const target = document.querySelector('#site-cart-target');
    if (!target || prefersReducedMotion()) return;

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
        document.querySelectorAll('[data-cart-count]').forEach((node) => { node.textContent = payload.cart_count; });
    }
    if (payload.cart_subtotal !== undefined) {
        document.querySelectorAll('[data-cart-subtotal]').forEach((node) => { node.textContent = payload.cart_subtotal; });
    }
    const target = document.querySelector('#site-cart-target');
    if (target && payload.cart_count !== undefined) target.setAttribute('aria-label', `购物车 ${payload.cart_count} 件`);
};

const setPreferenceButtonState = (button, type, active, label) => {
    if (!(button instanceof HTMLButtonElement)) return;

    const baseClass = 'inline-flex h-9 w-full items-center justify-center rounded-sm border';
    const activeClass = type === 'wishlist' ? 'border-pink-300 bg-pink-50 text-pink-800' : 'border-blue-300 bg-blue-50 text-blue-800';
    const inactiveClass = type === 'wishlist'
        ? 'border-slate-300 bg-white text-slate-700 hover:bg-pink-50 hover:text-pink-800'
        : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-800';
    button.className = `${baseClass} ${active ? activeClass : inactiveClass}`;
    button.dataset.preferenceActive = active ? 'true' : 'false';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
};

export const setupCartActions = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-cart-add-form]')) return;

        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button?.setAttribute('disabled', 'disabled');

        try {
            const payload = await fetchJson(form.action, {
                method: 'POST', body: new FormData(form), headers: { 'X-CSRF-TOKEN': csrfToken() },
            });
            refreshCartSummary(payload);
            animateToCart(button ?? form);
            flashStatus(payload.message ?? '已加入购物车。');
        } catch (error) {
            form.submit();
        } finally {
            button?.removeAttribute('disabled');
        }
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-product-preference-form]')) return;

        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button?.setAttribute('disabled', 'disabled');

        try {
            const payload = await fetchJson(form.action, {
                method: 'POST', body: new FormData(form), headers: { 'X-CSRF-TOKEN': csrfToken() },
            });
            const active = Boolean(payload.active);
            const label = payload.label ?? payload.message ?? '已更新';
            setPreferenceButtonState(button, payload.type, active, label);
            flashStatus(payload.message ?? '已更新。');
        } catch (error) {
            form.submit();
        } finally {
            button?.removeAttribute('disabled');
        }
    });
};
