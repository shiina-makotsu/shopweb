import { canUseBackgroundNetwork, createPrefetchQueue } from './core/prefetch-queue';
import { runIsolatedModule } from './core/runtime';

const normalizePath = (value) => {
    try {
        return new URL(value, window.location.origin).pathname.replace(/\/+$/, '') || '/';
    } catch (error) {
        return String(value || '').split('?')[0].replace(/\/+$/, '') || '/';
    }
};

const setupAdminNavigationPrefetch = () => {
    const runtimeConfig = window.shopwebAdminRuntime || {};
    const menu = runtimeConfig.menu || { items: [] };
    let pageReady = document.readyState === 'complete';
    let navigating = false;
    let started = false;
    const queue = createPrefetchQueue({
        storagePrefix: 'shopweb:admin-prefetch',
        purpose: 'admin-prefetch',
        timeoutMs: 9000,
        canRun: () => pageReady && !navigating && canUseBackgroundNetwork(),
    });
    const validAdminUrls = (urls, currentPath) => [...new Set(urls)].map((value) => {
        try { return new URL(value, window.location.origin); } catch (error) { return null; }
    }).filter((url) => url
        && url.origin === window.location.origin
        && url.pathname.startsWith('/admin')
        && !url.pathname.includes('/logout')
        && normalizePath(url.href) !== currentPath)
        .map((url) => url.href);
    const groupedUrls = () => {
        const currentPath = normalizePath(window.location.href);
        const links = Array.from(document.querySelectorAll('.fi-sidebar a[href]'));
        const active = links.find((link) => link.getAttribute('aria-current') === 'page' || normalizePath(link.href) === currentPath);
        const activeGroup = active?.closest('.fi-sidebar-group');
        const primaryLinks = activeGroup ? Array.from(activeGroup.querySelectorAll('a[href]')) : links.filter((link) => link.getClientRects().length > 0);
        const primary = validAdminUrls(primaryLinks.map((link) => link.href), currentPath);
        const primaryPaths = new Set(primary.map(normalizePath));
        const secondary = validAdminUrls([...links.map((link) => link.href), ...(menu.items || []).map((item) => item.url).filter(Boolean)], currentPath)
            .filter((url) => !primaryPaths.has(normalizePath(url)));
        return { primary, secondary };
    };
    const schedule = () => {
        if (started || navigating || !pageReady) return;
        started = true;
        const start = () => {
            const groups = groupedUrls();
            queue.enqueue([...groups.primary.slice(0, 8), ...groups.secondary.slice(0, 12)], { immediate: true });
        };
        if ('requestIdleCallback' in window) window.requestIdleCallback(start, { timeout: 3500 });
        else window.setTimeout(start, 1200);
    };
    const bindHints = () => {
        document.querySelectorAll('.fi-sidebar a[href]').forEach((link) => {
            if (link.dataset.shopwebPrefetchBound === 'true') return;
            link.dataset.shopwebPrefetchBound = 'true';
            const prefetch = () => queue.enqueue(validAdminUrls([link.href], normalizePath(window.location.href)), { immediate: true });
            link.addEventListener('pointerenter', prefetch, { passive: true });
            link.addEventListener('touchstart', prefetch, { passive: true });
            link.addEventListener('focus', prefetch, { passive: true });
        });
    };
    const ready = () => {
        pageReady = true;
        navigating = false;
        bindHints();
        window.setTimeout(schedule, 1200);
    };

    if (pageReady) ready();
    else window.addEventListener('load', ready, { once: true });
    document.addEventListener('livewire:navigating', () => {
        navigating = true;
        pageReady = false;
        queue.pause({ clear: true });
    });
    document.addEventListener('livewire:navigated', () => {
        started = false;
        queue.reset();
        ready();
    });
    document.addEventListener('livewire:update', bindHints);
};

runIsolatedModule('admin-navigation-prefetch', setupAdminNavigationPrefetch);
