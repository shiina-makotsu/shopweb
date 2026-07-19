import { csrfToken, fetchJson } from '../core/http';
import { flashStatus } from './ui';

export const setupGuidePet = () => {
    const root = document.querySelector('[data-guide-pet]');
    if (!root) return;

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

    if (!toggle || !panel || !form || !input || !messages || !chatUrl) return;

    const path = window.location.pathname;
    const context = {
        url: window.location.href,
        title: document.title.replace(/\s+-\s+.*$/, ''),
        type: path.includes('/products/') ? 'product'
            : path.includes('/cart') ? 'cart'
                : path.includes('/checkout') || path.includes('/orders/') ? 'checkout'
                    : path.includes('/forum') ? 'forum'
                        : path.includes('/ai-image') ? 'ai'
                            : path.includes('/support') ? 'support' : 'storefront',
        summary: Array.from(document.querySelectorAll('main h1, main h2, main h3, main p, main li, main td'))
            .map((node) => node.textContent?.trim() ?? '').filter(Boolean).join(' ').replace(/\s+/g, ' ').slice(0, 1500),
    };
    const labels = {
        product: '已识别：商品页', cart: '已识别：购物车', checkout: '已识别：订单/结算页',
        forum: '已识别：论坛页', ai: '已识别：AI 页', support: '已识别：客服页', storefront: '已识别：前台页面',
    };

    if (pageLabel) pageLabel.textContent = labels[context.type] ?? labels.storefront;

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
        if (open) input.focus();
    };
    const showBubble = (message) => {
        if (!bubble || !bubbleText || !panel.classList.contains('hidden')) return;
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
    const send = async (message) => {
        const text = message.trim();
        if (!text) return;

        addMessage(text, 'user');
        input.value = '';
        addMessage('我正在看当前页面...', 'assistant');
        const pending = messages.lastElementChild;

        try {
            const payload = await fetchJson(chatUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ message: text, page: context }),
            });
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

    window.setTimeout(() => showBubble(`${labels[context.type] ?? labels.storefront}。需要我介绍一下吗？`), 45000);
    avoidControls();
    window.addEventListener('resize', avoidControls);
    window.addEventListener('scroll', avoidControls, { passive: true });
    window.setInterval(avoidControls, 2500);
};
