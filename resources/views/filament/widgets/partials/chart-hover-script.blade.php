@once
    <script>
        (() => {
            const bindShopChartTabs = () => {
                document.querySelectorAll('[data-shop-chart-tabs]').forEach((root) => {
                    if (root.dataset.chartTabsBound === 'true') {
                        return;
                    }

                    root.dataset.chartTabsBound = 'true';

                    const buttons = Array.from(root.querySelectorAll('[data-shop-chart-tab-button]'));
                    const panels = Array.from(root.querySelectorAll('[data-shop-chart-tab-panel]'));

                    buttons.forEach((button) => {
                        button.addEventListener('click', () => {
                            const key = button.getAttribute('data-shop-chart-tab-button');

                            buttons.forEach((item) => item.classList.toggle('is-active', item === button));
                            panels.forEach((panel) => panel.classList.toggle('hidden', panel.getAttribute('data-shop-chart-tab-panel') !== key));
                        });
                    });
                });
            };

            const bindShopCharts = () => {
                bindShopChartTabs();

                document.querySelectorAll('[data-shop-chart-frame]').forEach((frame) => {
                    if (frame.dataset.chartBound === 'true') {
                        return;
                    }

                    frame.dataset.chartBound = 'true';

                    const svg = frame.querySelector('[data-shop-chart-svg]');
                    const tooltip = frame.querySelector('[data-shop-chart-tooltip]');
                    const title = frame.querySelector('[data-shop-chart-tooltip-title]');
                    const lines = Array.from(frame.querySelectorAll('[data-shop-chart-tooltip-line]'));
                    const points = Array.from(frame.querySelectorAll('[data-shop-chart-point]'));

                    if (!svg || !tooltip || !title || lines.length === 0 || points.length === 0) {
                        return;
                    }

                    const hide = () => {
                        tooltip.style.display = 'none';
                        tooltip.classList.add('hidden');
                        points.forEach((point) => {
                            point.classList.remove('is-active');
                            point.querySelectorAll('.shop-chart-point-dot, .shop-chart-point-ring').forEach((node) => {
                                node.style.opacity = '0';
                            });
                        });
                    };

                    const groupNearPoint = (point) => {
                        const pointX = Number(point.dataset.chartX ?? '0');
                        const pointY = Number(point.dataset.chartY ?? '0');

                        return points.filter((item) => {
                            const itemX = Number(item.dataset.chartX ?? '0');
                            const itemY = Number(item.dataset.chartY ?? '0');

                            return Math.abs(itemX - pointX) <= 0.5 && Math.abs(itemY - pointY) <= 0.5;
                        });
                    };

                    const mergedLinesFor = (group) => group
                        .flatMap((item) => Array.from({ length: lines.length }, (_, index) => item.getAttribute(`data-chart-line-${index + 1}`) ?? ''))
                        .map((value) => value.trim())
                        .filter(Boolean)
                        .filter((value, index, values) => values.indexOf(value) === index);

                    const setLines = (point) => {
                        const group = groupNearPoint(point);
                        const values = group.length > 1 ? mergedLinesFor(group) : null;

                        title.textContent = point.dataset.chartTitle ?? '';

                        lines.forEach((line, index) => {
                            const value = values
                                ? (values[index] ?? '')
                                : point.getAttribute(`data-chart-line-${index + 1}`) ?? '';

                            line.textContent = value;
                            line.hidden = value === '';
                        });
                    };

                    const chartPointPosition = (point) => {
                        const frameRect = frame.getBoundingClientRect();
                        const svgRect = svg.getBoundingClientRect();
                        const pointX = Number(point.dataset.chartX ?? '0');
                        const pointY = Number(point.dataset.chartY ?? '0');

                        return {
                            x: svgRect.left - frameRect.left + (svgRect.width * (pointX / 1000)),
                            y: svgRect.top - frameRect.top + (svgRect.height * (pointY / 260)),
                        };
                    };

                    const show = (point) => {
                        const frameRect = frame.getBoundingClientRect();
                        const position = chartPointPosition(point);
                        const offset = 14;

                        setLines(point);
                        const activeGroup = new Set(groupNearPoint(point));

                        points.forEach((item) => {
                            const active = activeGroup.has(item);

                            item.classList.toggle('is-active', active);
                            item.querySelectorAll('.shop-chart-point-dot, .shop-chart-point-ring').forEach((node) => {
                                node.style.opacity = active ? '1' : '0';
                            });
                        });

                        tooltip.style.display = 'block';
                        tooltip.classList.remove('hidden');

                        const width = tooltip.offsetWidth || 220;
                        const height = tooltip.offsetHeight || 120;
                        let left = position.x + offset;
                        let top = position.y - height - offset;

                        if (left + width > frameRect.width - 8) {
                            left = position.x - width - offset;
                        }

                        if (top < 8) {
                            top = position.y + offset;
                        }

                        tooltip.style.left = `${Math.max(8, Math.min(left, frameRect.width - width - 8))}px`;
                        tooltip.style.top = `${Math.max(8, top)}px`;
                    };

                    const nearestPoint = (event) => {
                        const svgRect = svg.getBoundingClientRect();
                        const x = svgRect.width > 0 ? ((event.clientX - svgRect.left) / svgRect.width) * 1000 : 0;
                        const y = svgRect.height > 0 ? ((event.clientY - svgRect.top) / svgRect.height) * 260 : 0;
                        let nearest = null;
                        let nearestDistance = Infinity;

                        points.forEach((point) => {
                            const pointX = Number(point.dataset.chartX ?? '0');
                            const pointY = Number(point.dataset.chartY ?? '0');
                            const dx = Math.abs(x - pointX);
                            const dy = Math.abs(y - pointY);
                            const distance = Math.sqrt((dx * dx) + (dy * dy));

                            if (dx <= 10 && dy <= 8 && distance < nearestDistance) {
                                nearest = point;
                                nearestDistance = distance;
                            }
                        });

                        return nearest;
                    };

                    const handleMove = (event) => {
                        const point = nearestPoint(event);

                        if (point) {
                            show(point);
                            return;
                        }

                        if (!tooltip.matches(':hover')) {
                            hide();
                        }
                    };

                    points.forEach((point) => {
                        const hit = point.querySelector('[data-shop-chart-hit-target]');

                        hit?.addEventListener('mouseenter', () => show(point));
                        hit?.addEventListener('mousemove', () => show(point));
                    });

                    frame.addEventListener('mousemove', handleMove);
                    frame.addEventListener('pointermove', handleMove);
                    frame.addEventListener('mouseleave', hide);
                    frame.addEventListener('contextmenu', (event) => {
                        event.preventDefault();
                        hide();
                    });

                    tooltip.addEventListener('mouseleave', hide);
                    hide();
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindShopCharts);
            } else {
                bindShopCharts();
            }

            document.addEventListener('livewire:init', bindShopCharts);
            document.addEventListener('livewire:navigated', bindShopCharts);
        })();
    </script>
@endonce
