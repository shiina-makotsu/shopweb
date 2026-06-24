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

const flashStatus = (message) => {
    flashCartStatus(message);
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

const setupFloatingCart = () => {
    const panel = document.querySelector('[data-floating-cart-panel]');
    const toggle = document.querySelector('[data-floating-cart-toggle]');
    const close = document.querySelector('[data-floating-cart-close]');

    if (!panel || !toggle) {
        return;
    }

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
    close?.addEventListener('click', () => setOpen(false));

    document.addEventListener('click', (event) => {
        if (panel.classList.contains('hidden')) {
            return;
        }

        if (panel.contains(event.target) || toggle.contains(event.target)) {
            return;
        }

        setOpen(false);
    });
};

setupFloatingCart();

const setupGuidePet = () => {
    const root = document.querySelector('[data-guide-pet]');

    if (!root) {
        return;
    }

    const toggle = root.querySelector('[data-guide-toggle]');
    const panel = root.querySelector('[data-guide-panel]');
    const close = root.querySelector('[data-guide-close]');
    const form = root.querySelector('[data-guide-form]');
    const input = root.querySelector('[data-guide-input]');
    const voice = root.querySelector('[data-guide-voice]');
    const messages = root.querySelector('[data-guide-messages]');
    const bubble = root.querySelector('[data-guide-bubble]');
    const bubbleText = root.querySelector('[data-guide-bubble-text]');
    const pageLabel = root.querySelector('[data-guide-page-label]');
    const chatUrl = root.dataset.guideChatUrl;

    if (!toggle || !panel || !form || !input || !messages || !chatUrl) {
        return;
    }

    const pageContext = () => {
        const path = window.location.pathname;
        const text = Array.from(document.querySelectorAll('main h1, main h2, main h3, main p, main li, main td'))
            .map((node) => node.textContent?.trim() ?? '')
            .filter(Boolean)
            .join(' ')
            .replace(/\s+/g, ' ')
            .slice(0, 1500);
        let type = 'storefront';

        if (path.includes('/products/')) type = 'product';
        else if (path.includes('/cart')) type = 'cart';
        else if (path.includes('/checkout') || path.includes('/orders/')) type = 'checkout';
        else if (path.includes('/forum')) type = 'forum';
        else if (path.includes('/ai-image')) type = 'ai';
        else if (path.includes('/support')) type = 'support';

        return {
            url: window.location.href,
            title: document.title.replace(/\s+-\s+.*$/, ''),
            type,
            summary: text,
        };
    };

    const context = pageContext();
    const labels = {
        product: '已识别：商品页',
        cart: '已识别：购物车',
        checkout: '已识别：订单/结算页',
        forum: '已识别：论坛页',
        ai: '已识别：AI 页',
        support: '已识别：客服页',
        storefront: '已识别：前台页面',
    };

    if (pageLabel) {
        pageLabel.textContent = labels[context.type] ?? labels.storefront;
    }

    const addMessage = (body, from = 'assistant') => {
        const item = document.createElement('div');
        item.className = `shop-guide-message shop-guide-message-${from}`;
        item.textContent = body;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    };

    const setOpen = (open) => {
        panel.classList.toggle('hidden', !open);
        bubble?.classList.add('hidden');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            input.focus();
        }
    };

    const showBubble = (message) => {
        if (!bubble || !bubbleText || !panel.classList.contains('hidden')) {
            return;
        }

        bubbleText.textContent = message;
        bubble.classList.remove('hidden');
        window.setTimeout(() => bubble.classList.add('hidden'), 8000);
    };

    const avoidControls = () => {
        const controls = Array.from(document.querySelectorAll('#site-cart-target, [data-floating-cart-toggle], a[href="#top"], [data-mobile-menu-open], button, a'))
            .filter((node) => node instanceof HTMLElement && node.offsetParent !== null);
        const rootRect = root.getBoundingClientRect();
        const overlap = controls.some((node) => {
            if (root.contains(node)) return false;
            const rect = node.getBoundingClientRect();
            return !(rootRect.right < rect.left || rootRect.left > rect.right || rootRect.bottom < rect.top || rootRect.top > rect.bottom);
        });

        root.classList.toggle('shop-guide-pet-left', overlap);
    };

    const send = async (message, guided = false) => {
        const text = message.trim();

        if (text === '') {
            return;
        }

        addMessage(text, 'user');
        input.value = '';
        addMessage(guided ? '我会按你的引导继续处理。' : '我正在看当前页面...', 'assistant');
        const pending = messages.lastElementChild;

        try {
            const response = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    message: text,
                    page: context,
                }),
            });

            const payload = await response.json();
            pending.textContent = payload.message ?? '导购助手暂时没有返回内容。';
        } catch (error) {
            pending.textContent = '导购助手连接失败，你可以稍后再试，或直接联系客服。';
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
    close?.addEventListener('click', () => setOpen(false));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        send(input.value);
    });

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (voice && SpeechRecognition) {
        voice.addEventListener('click', () => {
            const recognition = new SpeechRecognition();
            recognition.lang = document.documentElement.lang || 'zh-CN';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            recognition.onresult = (event) => {
                input.value = event.results?.[0]?.[0]?.transcript ?? '';
                input.focus();
            };
            recognition.onerror = () => flashStatus('语音输入失败，请检查浏览器权限。');
            recognition.start();
        });
    } else if (voice) {
        voice.addEventListener('click', () => flashStatus('当前浏览器不支持语音输入。'));
    }

    window.setTimeout(() => {
        showBubble(`${labels[context.type] ?? labels.storefront}。需要我介绍一下吗？`);
    }, 45000);

    avoidControls();
    window.addEventListener('resize', avoidControls);
    window.addEventListener('scroll', avoidControls, { passive: true });
    window.setInterval(avoidControls, 2500);
};

setupGuidePet();

const setPreferenceButtonState = (button, type, active, label) => {
    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    const baseClass = 'inline-flex h-9 w-full items-center justify-center rounded-sm border';
    const activeClass = type === 'wishlist'
        ? 'border-pink-300 bg-pink-50 text-pink-800'
        : 'border-blue-300 bg-blue-50 text-blue-800';
    const inactiveClass = type === 'wishlist'
        ? 'border-slate-300 bg-white text-slate-700 hover:bg-pink-50 hover:text-pink-800'
        : 'border-slate-300 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-800';

    button.className = `${baseClass} ${active ? activeClass : inactiveClass}`;
    button.dataset.preferenceActive = active ? 'true' : 'false';
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
};

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

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-product-preference-form]')) {
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
            throw new Error('preference-failed');
        }

        const payload = await response.json();
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
