<script>
    window.navigationMenuTreeManager = window.navigationMenuTreeManager || (({ trees, save }) => ({
        trees,
        dragged: null,
        saved: false,
        dragStart(placement, id) {
            this.dragged = { placement, id };
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
        dropOnList(placement, parentId) {
            if (! this.dragged || this.dragged.placement !== placement) {
                return;
            }

            const root = this.trees[placement].items;
            const item = this.findAndRemove(root, this.dragged.id);

            if (! item) {
                return;
            }

            this.normalize(item);

            if (parentId === null) {
                root.push(item);
                this.dragged = null;
                this.saveTree(placement);

                return;
            }

            const parent = this.findItem(root, parentId);

            if (! parent || parent.id === item.id || this.contains(item.children_recursive, parent.id)) {
                root.push(item);
                this.dragged = null;

                return;
            }

            parent.children_recursive = parent.children_recursive || [];
            parent.children_recursive.push(item);
            this.dragged = null;
            this.saveTree(placement);
        },
        dropOnItem(placement, targetId) {
            if (! this.dragged || this.dragged.placement !== placement || this.dragged.id === targetId) {
                return;
            }

            const root = this.trees[placement].items;
            const item = this.findAndRemove(root, this.dragged.id);

            if (! item) {
                return;
            }

            const inserted = this.insertBefore(root, targetId, this.normalize(item));

            if (! inserted) {
                root.push(item);
            }

            this.dragged = null;
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
            save(placement, this.serialize(this.trees[placement].items));
        },
    }));
</script>

<div
    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
    x-data="window.navigationMenuTreeManager({
        trees: @js($treePage->getNavigationMenuTree()),
        save: (placement, items) => $wire.saveNavigationMenuTree(placement, items),
    })"
    x-on:navigation-menu-tree-saved.window="saved = true; setTimeout(() => saved = false, 1800)"
>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950">菜单目录树</h2>
            <p class="mt-1 text-sm text-gray-500">拖动菜单项可调整显示顺序，也可以拖入其他菜单项下方作为二级菜单。没有链接的菜单项适合作为目录。</p>
        </div>
        <span x-show="saved" x-cloak class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">已保存</span>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <template x-for="(tree, placement) in trees" :key="placement">
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
                    class="min-h-16 space-y-2 rounded-md border border-dashed border-gray-300 bg-white p-2"
                    x-on:dragover.prevent
                    x-on:drop.prevent="dropOnList(placement, null)"
                >
                    <template x-for="item in tree.items" :key="item.id">
                        <li>
                            <div
                                class="flex cursor-move items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                                draggable="true"
                                x-on:dragstart="dragStart(placement, item.id)"
                                x-on:dragover.prevent
                                x-on:drop.prevent="dropOnItem(placement, item.id)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900" x-text="item.label"></p>
                                    <p class="mt-0.5 truncate text-xs text-gray-500" x-text="item.route_name || item.url || '无页面上级菜单'"></p>
                                </div>
                                <span class="text-gray-400">↕</span>
                            </div>
                            <ol
                                class="ml-5 mt-2 min-h-10 space-y-2 rounded-md border border-dashed border-gray-200 bg-gray-50 p-2"
                                x-on:dragover.prevent
                                x-on:drop.prevent="dropOnList(placement, item.id)"
                            >
                                <template x-for="child in (item.children_recursive || [])" :key="child.id">
                                    <li
                                        class="flex cursor-move items-center justify-between gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm"
                                        draggable="true"
                                        x-on:dragstart="dragStart(placement, child.id)"
                                        x-on:dragover.prevent
                                        x-on:drop.prevent="dropOnItem(placement, child.id)"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-gray-900" x-text="child.label"></p>
                                            <p class="mt-0.5 truncate text-xs text-gray-500" x-text="child.route_name || child.url || '无页面菜单'"></p>
                                        </div>
                                        <span class="text-gray-400">↕</span>
                                    </li>
                                </template>
                            </ol>
                        </li>
                    </template>
                    <li x-show="tree.items.length === 0" class="rounded-md bg-gray-50 px-3 py-6 text-center text-sm text-gray-500">暂无菜单项。</li>
                </ol>
            </section>
        </template>
    </div>
</div>
