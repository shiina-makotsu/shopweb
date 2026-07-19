import { canUseBackgroundNetwork, createPrefetchQueue } from '../core/prefetch-queue';

const normalizeUrl = (value) => {
    const url = new URL(value, window.location.origin);
    url.hash = '';
    return url.href;
};

const eligibleUrl = (link) => {
    if (!(link instanceof HTMLAnchorElement) || link.target === '_blank' || link.hasAttribute('download') || link.dataset.noPrefetch !== undefined) {
        return null;
    }

    try {
        const url = new URL(link.href, window.location.origin);
        const excluded = ['/admin', '/login', '/register', '/logout', '/loading', '/checkout', '/payment'];

        if (!['http:', 'https:'].includes(url.protocol)
            || url.origin !== window.location.origin
            || excluded.some((path) => url.pathname === path || url.pathname.startsWith(`${path}/`))
            || normalizeUrl(url.href) === normalizeUrl(window.location.href)) {
            return null;
        }

        return normalizeUrl(url.href);
    } catch (error) {
        return null;
    }
};

export const setupStorefrontNavigationPrefetch = () => {
    let pageReady = document.readyState === 'complete';
    const queue = createPrefetchQueue({
        storagePrefix: 'shopweb:storefront-prefetch',
        purpose: 'storefront-prefetch',
        canRun: () => pageReady && canUseBackgroundNetwork(),
    });
    const enqueueLinks = (links, immediate = false) => {
        queue.enqueue((Array.isArray(links) ? links : [links]).map(eligibleUrl).filter(Boolean), { immediate });
    };
    const schedule = () => {
        const start = () => {
            const links = Array.from(document.querySelectorAll('header nav a[href], main aside nav a[href]'));
            const visible = links.filter((link) => link.getClientRects().length > 0);
            const hidden = links.filter((link) => link.getClientRects().length === 0);
            enqueueLinks([...visible, ...hidden].slice(0, 12));
            queue.run();
        };

        if ('requestIdleCallback' in window) window.requestIdleCallback(start, { timeout: 4000 });
        else window.setTimeout(start, 1200);
    };
    const prefetchEventLink = (event) => {
        const link = event.target.closest?.('a[href]');
        if (link) enqueueLinks(link, true);
    };

    document.addEventListener('pointerover', prefetchEventLink, { passive: true });
    document.addEventListener('focusin', prefetchEventLink);
    document.addEventListener('touchstart', prefetchEventLink, { passive: true });
    window.addEventListener('pagehide', queue.stop, { once: true });

    if (pageReady) schedule();
    else window.addEventListener('load', () => {
        pageReady = true;
        schedule();
    }, { once: true });
};
