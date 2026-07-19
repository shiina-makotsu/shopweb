const connection = () => navigator.connection || navigator.mozConnection || navigator.webkitConnection;

export const canUseBackgroundNetwork = () => {
    const network = connection();

    return Boolean(window.fetch && window.AbortController)
        && document.visibilityState === 'visible'
        && !network?.saveData
        && !['slow-2g', '2g'].includes(network?.effectiveType);
};

export const createPrefetchQueue = ({
    storagePrefix,
    purpose,
    cooldownMs = 10 * 60 * 1000,
    betweenMs = 700,
    timeoutMs = 8000,
    canRun = canUseBackgroundNetwork,
}) => {
    const state = { queue: [], active: false, controller: null, timer: null, stopped: false };
    const key = (url) => `${storagePrefix}:${url}`;
    const recentlyFetched = (url) => {
        try {
            const timestamp = Number(sessionStorage.getItem(key(url)) || 0);

            return timestamp > 0 && Date.now() - timestamp < cooldownMs;
        } catch (error) {
            return false;
        }
    };
    const remember = (url) => {
        try {
            sessionStorage.setItem(key(url), String(Date.now()));
        } catch (error) {
            // Session storage is optional in hardened browser contexts.
        }
    };
    const run = () => {
        if (state.stopped || state.active || state.queue.length === 0 || !canRun()) {
            return;
        }

        const url = state.queue.shift();

        if (!url || recentlyFetched(url)) {
            run();
            return;
        }

        state.active = true;
        remember(url);
        state.controller = new AbortController();
        const timeout = window.setTimeout(() => state.controller?.abort(), timeoutMs);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'force-cache',
            priority: 'low',
            signal: state.controller.signal,
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-ShopWeb-Purpose': purpose,
            },
        }).then((response) => response.ok ? response.text() : null).catch(() => {
            // A failed prefetch must never affect interactive navigation.
        }).finally(() => {
            window.clearTimeout(timeout);
            state.controller = null;
            state.active = false;
            state.timer = window.setTimeout(run, betweenMs);
        });
    };
    const enqueue = (urls, { immediate = false } = {}) => {
        const known = new Set(state.queue);
        const additions = [...new Set(Array.isArray(urls) ? urls : [urls])]
            .filter((url) => url && !known.has(url) && !recentlyFetched(url));

        state.queue = immediate ? [...additions, ...state.queue] : [...state.queue, ...additions];

        if (immediate) run();
    };
    const pause = ({ clear = false } = {}) => {
        state.controller?.abort();
        window.clearTimeout(state.timer);
        if (clear) state.queue = [];
    };
    const reset = () => {
        pause({ clear: true });
        state.stopped = false;
    };
    const stop = () => {
        state.stopped = true;
        pause({ clear: true });
    };

    return { enqueue, pause, reset, run, stop, state };
};
