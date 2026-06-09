<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">数据库备份</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                下载当前数据库。SQLite 会下载数据库文件，MySQL/MariaDB 会生成基础 SQL。
            </p>
            <a
                href="{{ route('admin.backups.database') }}"
                target="_blank"
                class="mt-4 inline-flex min-h-10 items-center rounded-sm bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700"
            >
                下载数据库
            </a>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">上传资源备份</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                下载 public/uploads 下的公开图片、文件、Logo、背景图和展示资料。
            </p>
            <a
                href="{{ route('admin.backups.uploads') }}"
                target="_blank"
                class="mt-4 inline-flex min-h-10 items-center rounded-sm bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700"
            >
                下载上传资源
            </a>
        </section>
    </div>
</x-filament-panels::page>
