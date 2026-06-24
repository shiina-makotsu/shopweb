<script>
    window.navigationMenuTreeManager = window.navigationMenuTreeManager || (({ frontendTrees, adminTrees, saveFrontend, saveAdmin }) => ({
        activeTab: 'frontend',
        frontendTrees,
        adminTrees,
        dragged: null,
        dropHint: null,
        dropTarget: null,
        pointerDragging: false,
        saved: false,
        scrollTimer: null,
        currentTrees() {
            return this.activeTab === 'admin' ? this.adminTrees : this.frontendTrees;
        },
        currentSave() {
            return this.activeTab === 'admin' ? saveAdmin : saveFrontend;
        },
        dragStart(placement, id) {
            this.dragged = { tab: this.activeTab, placement, id };
            this.dropHint = null;
            this.dropTarget = null;
        },
        startPointerDrag(event, placement, id) {
            if (event.button !== 0 || event.target.closest('button, a, input, select, textarea')) {
                return;
            }

            event.preventDefault();
            this.pointerDragging = true;
            this.dragStart(placement, id);
        },
        pointerMove(event) {
            if (! this.pointerDragging || ! this.dragged) {
                return;
            }

            this.dragMove(event);

            const target = this.resolveDropTarget(event);

            if (target) {
                this.previewDropTarget(target);
            }
        },
        pointerEnd(event) {
            if (! this.pointerDragging) {
                return;
            }

            const target = this.resolveDropTarget(event);
            this.pointerDragging = false;

            if (! target) {
                this.dragged = null;
                this.clearDropHint();
                this.stopAutoScroll();

                return;
            }

            if (target.mode === 'before') {
                this.dropOnItem(target.placement, target.id);

                return;
            }

            this.dropOnList(target.placement, target.mode === 'root' ? null : target.id);
        },
        resolveDropTarget(event) {
            const element = document.elementFromPoint(event.clientX, event.clientY);
            const target = element?.closest?.('[data-menu-drop]');

            if (! target) {
                return null;
            }

            return {
                placement: target.dataset.placement,
                id: target.dataset.dropId ? Number(target.dataset.dropId) : null,
                mode: target.dataset.dropMode,
            };
        },
        previewDropTarget(target) {
            const tree = this.currentTrees()[target.placement];

            if (! tree) {
                return;
            }

            if (target.mode === 'root') {
                this.previewRootDrop(target.placement, tree);

                return;
            }

            const item = this.findItem(tree.items, target.id);

            if (! item || Number(item.id) === Number(this.dragged?.id)) {
                return;
            }

            if (target.mode === 'child') {
                this.previewChildDrop(target.placement, item);

                return;
            }

            this.previewBeforeDrop(target.placement, item);
        },
        dragMove(event) {
            if (! this.dragged) {
                return;
            }

            const threshold = 96;
            const speed = 18;
            const y = event.clientY;
            const height = window.innerHeight;
            let delta = 0;

            if (y < threshold) {
                delta = -speed;
            } else if (y > height - threshold) {
                delta = speed;
            }

            if (delta === 0) {
                this.stopAutoScroll();
                return;
            }

            if (this.scrollTimer) {
                return;
            }

            this.scrollTimer = window.setInterval(() => window.scrollBy({ top: delta, behavior: 'auto' }), 16);
        },
        dragWheel(event) {
            if (! this.dragged) {
                return;
            }

            const scrollBox = event.target?.closest?.('[data-menu-scroll]');

            if (scrollBox) {
                scrollBox.scrollTop += event.deltaY;

                return;
            }

            window.scrollBy({ top: event.deltaY, behavior: 'auto' });
        },
        stopAutoScroll() {
            if (! this.scrollTimer) {
                return;
            }

            window.clearInterval(this.scrollTimer);
            this.scrollTimer = null;
        },
        setDropHint(text, target = null) {
            if (! this.dragged) {
                return;
            }

            this.dropHint = text;
            this.dropTarget = target;
        },
        previewRootDrop(placement, tree) {
            if (! this.dragged) {
                return;
            }

            const item = this.findItem(this.currentTrees()[this.dragged.placement].items, this.dragged.id);

            this.setDropHint(
                item && ! this.canDropOnRoot(item) ? '后台页面需要放在某个一级菜单下' : `将移动到 ${tree.label} 一级菜单末尾`,
                { placement, id: null, mode: 'root' },
            );
        },
        previewChildDrop(placement, parent) {
            if (! this.dragged) {
                return;
            }

            const item = this.findItem(this.currentTrees()[this.dragged.placement].items, this.dragged.id);

            this.setDropHint(
                item && ! this.canDropIntoParent(item, parent) ? '后台一级菜单不能作为二级菜单' : `将作为 ${parent.label} 的二级菜单`,
                { placement, id: parent.id, mode: 'child' },
            );
        },
        previewBeforeDrop(placement, target) {
            if (! this.dragged) {
                return;
            }

            const item = this.findItem(this.currentTrees()[this.dragged.placement].items, this.dragged.id);

            this.setDropHint(
                item && ! this.canDropBefore(item, target) ? '该菜单不能插入到此层级' : `将移动到 ${target.label} 前方`,
                { placement, id: target.id, mode: 'before' },
            );
        },
        clearDropHint() {
            this.dropHint = null;
            this.dropTarget = null;
        },
        isDropTarget(placement, id, mode) {
            return this.dropTarget
                && this.dropTarget.placement === placement
                && this.dropTarget.mode === mode
                && String(this.dropTarget.id ?? '') === String(id ?? '');
        },
        isDraggingItem(id) {
            return this.dragged && Number(this.dragged.id) === Number(id);
        },
        findAndRemove(items, id) {
            for (let index = 0; index < items.length; index += 1) {
                if (items[index].id === id) {
                    return items.splice(index, 1)[0];
                }

                const child = this.findAndRemove(items[index].children_recursive || [], id);

                if (child) {
                    return child;
                }
            }

            return null;
        },
        contains(items, id) {
            return items.some((item) => item.id === id || this.contains(item.children_recursive || [], id));
        },
        normalize(item) {
            item.children_recursive = item.children_recursive || [];

            return item;
        },
        canDropOnRoot(item) {
            return this.activeTab !== 'admin' || item.type === 'group';
        },
        canDropIntoParent(item, parent) {
            return this.activeTab !== 'admin' || (item.type === 'item' && parent.type === 'group');
        },
        canDropBefore(item, target) {
            return this.activeTab !== 'admin' || item.type === target.type;
        },
        dropOnList(placement, parentId) {
            if (! this.dragged || this.dragged.tab !== this.activeTab || this.dragged.placement !== placement) {
                return;
            }

            const root = this.currentTrees()[placement].items;
            const item = this.findAndRemove(root, this.dragged.id);

            if (! item) {
                return;
            }

            this.normalize(item);

            if (parentId === null) {
                if (! this.canDropOnRoot(item)) {
                    root.push(item);
                    this.dragged = null;
                    this.clearDropHint();
                    this.stopAutoScroll();

                    return;
                }

                root.push(item);
                this.dragged = null;
                this.clearDropHint();
                this.stopAutoScroll();
                this.saveTree(placement);

                return;
            }

            const parent = this.findItem(root, parentId);

            if (! parent || parent.id === item.id || this.contains(item.children_recursive, parent.id) || ! this.canDropIntoParent(item, parent)) {
                root.push(item);
                this.dragged = null;
                this.clearDropHint();
                this.stopAutoScroll();

                return;
            }

            parent.children_recursive = parent.children_recursive || [];
            parent.children_recursive.push(item);
            this.dragged = null;
            this.clearDropHint();
            this.stopAutoScroll();
            this.saveTree(placement);
        },
        dropOnItem(placement, targetId) {
            if (! this.dragged || this.dragged.tab !== this.activeTab || this.dragged.placement !== placement || this.dragged.id === targetId) {
                return;
            }

            const root = this.currentTrees()[placement].items;
            const item = this.findAndRemove(root, this.dragged.id);

            if (! item) {
                return;
            }

            const target = this.findItem(root, targetId);

            if (! target || ! this.canDropBefore(item, target)) {
                root.push(item);
                this.dragged = null;
                this.clearDropHint();
                this.stopAutoScroll();

                return;
            }

            const inserted = this.insertBefore(root, targetId, this.normalize(item));

            if (! inserted) {
                root.push(item);
            }

            this.dragged = null;
            this.clearDropHint();
            this.stopAutoScroll();
            this.saveTree(placement);
        },
        findItem(items, id) {
            for (const item of items) {
                if (item.id === id) {
                    return item;
                }

                const child = this.findItem(item.children_recursive || [], id);

                if (child) {
                    return child;
                }
            }

            return null;
        },
        insertBefore(items, targetId, item) {
            const index = items.findIndex((candidate) => candidate.id === targetId);

            if (index !== -1) {
                items.splice(index, 0, item);

                return true;
            }

            for (const candidate of items) {
                if (this.insertBefore(candidate.children_recursive || [], targetId, item)) {
                    return true;
                }
            }

            return false;
        },
        serialize(items) {
            return items.map((item) => ({
                id: item.id,
                children: this.serialize(item.children_recursive || []),
            }));
        },
        saveTree(placement) {
            this.currentSave()(placement, this.serialize(this.currentTrees()[placement].items));
        },
    }));
</script>

<div
    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
    x-data="window.navigationMenuTreeManager({
        frontendTrees: @js($treePage->getNavigationMenuTree()),
        adminTrees: @js($treePage->getAdminMenuTree()),
        saveFrontend: (placement, items) => $wire.saveNavigationMenuTree(placement, items),
        saveAdmin: (placement, items) => $wire.saveAdminMenuTree(placement, items),
    })"
    x-on:navigation-menu-tree-saved.window="saved = true; setTimeout(() => saved = false, 1800)"
    x-on:dragover.window="dragMove($event)"
    x-on:pointermove.window="pointerMove($event)"
    x-on:pointerup.window="pointerEnd($event)"
    x-on:wheel.window.passive="dragWheel($event)"
    x-on:dragend.window="stopAutoScroll(); if (! pointerDragging) { dragged = null; clearDropHint() }"
    x-on:drop.window="stopAutoScroll()"
>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950">菜单目录树</h2>
            <p class="mt-1 text-sm text-gray-500">拖动菜单项可调整显示顺序，也可以拖入一级菜单下方作为二级菜单。拖拽时可滚动页面，并会实时显示即将放置的位置。</p>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="dropHint" x-cloak class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700" x-text="dropHint"></span>
            <span x-show="saved" x-cloak class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">已保存</span>
        </div>
    </div>

    <div class="mb-4 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-sm">
        <button
            type="button"
            class="rounded-md px-3 py-1.5 font-medium"
            x-bind:class="activeTab === 'frontend' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            x-on:click="activeTab = 'frontend'; clearDropHint()"
        >
            前台菜单
        </button>
        <button
            type="button"
            class="rounded-md px-3 py-1.5 font-medium"
            x-bind:class="activeTab === 'admin' ? 'bg-white text-gray-950 shadow-sm' : 'text-gray-500 hover:text-gray-900'"
            x-on:click="activeTab = 'admin'; clearDropHint()"
        >
            后台菜单
        </button>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <template x-for="(tree, placement) in currentTrees()" :key="`${activeTab}-${placement}`">
            <section class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-900" x-text="tree.label"></h3>
                    <button
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100"
                        type="button"
                        x-on:click="saveTree(placement)"
                    >
                        保存排序
                    </button>
                </div>

                <ol
                    data-menu-scroll
                    data-menu-drop
                    x-bind:data-placement="placement"
                    data-drop-mode="root"
                    class="min-h-16 space-y-2 rounded-md border border-dashed border-gray-300 bg-white p-2"
                    x-on:dragover.prevent
                    x-on:dragenter="previewRootDrop(placement, tree)"
                    x-on:drop.prevent="dropOnList(placement, null)"
                >
                    <template x-for="item in tree.items" :key="item.id">
                        <li>
                            <div x-show="isDropTarget(placement, item.id, 'before')" x-cloak class="mb-2 flex items-center gap-2 text-xs font-medium text-blue-600">
                                <span class="h-0.5 flex-1 rounded-full bg-blue-500"></span>
                                <span>插入到此处</span>
                            </div>
                            <div
                                class="flex cursor-move items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                                x-bind:class="isDraggingItem(item.id) ? 'opacity-50 ring-2 ring-blue-400' : ''"
                                draggable="false"
                                data-menu-drop
                                x-bind:data-placement="placement"
                                data-drop-mode="before"
                                x-bind:data-drop-id="item.id"
                                x-on:pointerdown="startPointerDrag($event, placement, item.id)"
                                x-on:dragstart="dragStart(placement, item.id)"
                                x-on:dragover.prevent
                                x-on:dragenter="previewBeforeDrop(placement, item)"
                                x-on:drop.prevent="dropOnItem(placement, item.id)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900" x-text="item.label"></p>
                                    <p class="mt-0.5 truncate text-xs text-gray-500" x-text="item.route_name || item.url || '无页面上级菜单'"></p>
                                </div>
                                <span class="text-gray-400">↕</span>
                            </div>
                            <ol
                                data-menu-scroll
                                data-menu-drop
                                x-bind:data-placement="placement"
                                data-drop-mode="child"
                                x-bind:data-drop-id="item.id"
                                class="ml-5 mt-2 min-h-10 space-y-2 rounded-md border border-dashed border-gray-200 bg-gray-50 p-2"
                                x-on:dragover.prevent
                                x-on:dragenter="previewChildDrop(placement, item)"
                                x-on:drop.prevent="dropOnList(placement, item.id)"
                            >
                                <template x-for="child in (item.children_recursive || [])" :key="child.id">
                                    <li>
                                        <div x-show="isDropTarget(placement, child.id, 'before')" x-cloak class="mb-2 flex items-center gap-2 text-xs font-medium text-blue-600">
                                            <span class="h-0.5 flex-1 rounded-full bg-blue-500"></span>
                                            <span>插入到此处</span>
                                        </div>
                                        <div
                                            class="flex cursor-move items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                                            x-bind:class="isDraggingItem(child.id) ? 'opacity-50 ring-2 ring-blue-400' : ''"
                                            draggable="false"
                                            data-menu-drop
                                            x-bind:data-placement="placement"
                                            data-drop-mode="before"
                                            x-bind:data-drop-id="child.id"
                                            x-on:pointerdown="startPointerDrag($event, placement, child.id)"
                                            x-on:dragstart="dragStart(placement, child.id)"
                                            x-on:dragover.prevent
                                            x-on:dragenter="previewBeforeDrop(placement, child)"
                                            x-on:drop.prevent="dropOnItem(placement, child.id)"
                                        >
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-gray-900" x-text="child.label"></p>
                                                <p class="mt-0.5 truncate text-xs text-gray-500" x-text="child.route_name || child.url || '无页面菜单'"></p>
                                            </div>
                                            <span class="text-gray-400">↕</span>
                                        </div>
                                    </li>
                                </template>
                                <li x-show="isDropTarget(placement, item.id, 'child')" x-cloak class="flex items-center gap-2 text-xs font-medium text-blue-600">
                                    <span class="h-0.5 flex-1 rounded-full bg-blue-500"></span>
                                    <span>二级菜单末尾</span>
                                </li>
                            </ol>
                        </li>
                    </template>
                    <li x-show="isDropTarget(placement, null, 'root')" x-cloak class="flex items-center gap-2 text-xs font-medium text-blue-600">
                        <span class="h-0.5 flex-1 rounded-full bg-blue-500"></span>
                        <span>一级菜单末尾</span>
                    </li>
                    <li x-show="tree.items.length === 0" class="rounded-md bg-gray-50 px-3 py-6 text-center text-sm text-gray-500">暂无菜单项。</li>
                </ol>
            </section>
        </template>
    </div>
</div>
