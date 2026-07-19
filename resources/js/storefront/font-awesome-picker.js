const iconOptions = [
    ['fish-fins', '鱼板/鱼', 'solid'], ['cart-shopping', '购物车', 'solid'],
    ['heart', '爱心', 'regular'], ['star', '星标', 'regular'], ['bell', '公告', 'solid'],
    ['truck', '物流', 'solid'], ['box-open', '发货', 'solid'], ['wallet', '钱包', 'solid'],
    ['ticket', '优惠券', 'solid'], ['gift', '奖励', 'solid'], ['circle-check', '完成', 'regular'],
    ['clock', '等待', 'regular'], ['triangle-exclamation', '提醒', 'solid'],
    ['comment', '评论', 'regular'], ['paper-plane', '发送', 'solid'], ['image', '图片', 'regular'],
    ['user', '用户', 'regular'], ['users', '用户组', 'solid'], ['paypal', 'PayPal', 'brands'],
    ['github', 'GitHub', 'brands'],
];

export const fontAwesomeShortcode = (icon, style = 'solid', label = '') => {
    const normalizedIcon = String(icon || '').trim().toLowerCase().replace(/^fa-/, '');
    const normalizedStyle = ['solid', 'regular', 'brands'].includes(String(style).toLowerCase()) ? String(style).toLowerCase() : 'solid';
    const normalizedLabel = String(label || '').trim();

    if (!/^[a-z0-9][a-z0-9-]*$/.test(normalizedIcon)) return '';

    return normalizedStyle === 'solid'
        ? `[fa:${normalizedIcon}${normalizedLabel ? ` ${normalizedLabel}` : ''}]`
        : `[fa:${normalizedStyle}:${normalizedIcon}${normalizedLabel ? ` ${normalizedLabel}` : ''}]`;
};

const insertTextAtCursor = (field, text) => {
    if (!(field instanceof HTMLTextAreaElement) && !(field instanceof HTMLInputElement) || !text) return;

    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? field.value.length;
    field.value = `${field.value.slice(0, start)}${text}${field.value.slice(end)}`;
    field.selectionStart = field.selectionEnd = start + text.length;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.focus();
};

export const openFontAwesomePicker = (onSelect) => {
    document.querySelector('[data-shop-fa-picker]')?.remove();

    const overlay = document.createElement('div');
    overlay.dataset.shopFaPicker = 'true';
    overlay.className = 'shop-fa-picker-overlay';
    overlay.innerHTML = `
        <div class="shop-fa-picker" role="dialog" aria-modal="true" aria-label="选择 Font Awesome 图标">
            <div class="shop-fa-picker-head"><strong>选择 Font Awesome 图标</strong><button type="button" data-shop-fa-close aria-label="关闭">×</button></div>
            <div class="shop-fa-picker-fields">
                <input data-shop-fa-search placeholder="搜索常用图标或输入图标名，如 fish-fins" autocomplete="off">
                <select data-shop-fa-style><option value="solid">Solid</option><option value="regular">Regular</option><option value="brands">Brands</option></select>
                <input data-shop-fa-label placeholder="可选辅助说明">
            </div>
            <div class="shop-fa-picker-grid" data-shop-fa-grid></div>
            <div class="shop-fa-picker-actions"><button type="button" data-shop-fa-custom>插入自定义图标</button></div>
        </div>`;
    document.body.appendChild(overlay);

    const search = overlay.querySelector('[data-shop-fa-search]');
    const style = overlay.querySelector('[data-shop-fa-style]');
    const label = overlay.querySelector('[data-shop-fa-label]');
    const grid = overlay.querySelector('[data-shop-fa-grid]');
    const close = () => overlay.remove();
    const choose = (icon, iconStyle = style.value) => {
        const shortcode = fontAwesomeShortcode(icon, iconStyle, label.value);
        if (shortcode) onSelect(shortcode);
        close();
    };
    const renderGrid = () => {
        const query = String(search.value || '').trim().toLowerCase();
        const options = iconOptions.filter(([icon, text]) => !query || icon.includes(query) || text.toLowerCase().includes(query)).slice(0, 30);
        grid.innerHTML = options.map(([icon, text, iconStyle]) => {
            const family = { solid: 'fa-solid', regular: 'fa-regular', brands: 'fa-brands' }[iconStyle] || 'fa-solid';
            return `<button type="button" data-shop-fa-icon="${icon}" data-shop-fa-icon-style="${iconStyle}"><i class="${family} fa-${icon} fa-fw" aria-hidden="true"></i><span>${text}</span><small>${icon}</small></button>`;
        }).join('') || '<p>没有匹配的常用图标，可使用自定义插入。</p>';
    };

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay || event.target.closest('[data-shop-fa-close]')) return close();
        const option = event.target.closest('[data-shop-fa-icon]');
        if (option) choose(option.dataset.shopFaIcon, option.dataset.shopFaIconStyle);
        if (event.target.closest('[data-shop-fa-custom]')) choose(search.value, style.value);
    });
    search.addEventListener('input', renderGrid);
    overlay.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
    renderGrid();
    search.focus();
};

export const setupFontAwesomePicker = () => {
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-fa-textarea-target]');
        if (!(button instanceof HTMLButtonElement)) return;

        const field = button.dataset.faTextareaTarget
            ? document.querySelector(button.dataset.faTextareaTarget)
            : button.closest('form')?.querySelector('textarea[name="body"], input[name="body"], textarea[data-fa-textarea], input[data-fa-textarea]');

        openFontAwesomePicker((shortcode) => insertTextAtCursor(field, shortcode));
    });
};
