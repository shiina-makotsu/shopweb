import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

const normalizeManifestPath = (value) => {
    const normalized = value.replaceAll('\\', '/');
    const root = projectRoot.replaceAll('\\', '/');

    return normalized.startsWith(`${root}/`)
        ? normalized.slice(root.length + 1)
        : normalized;
};

const normalizeLaravelManifest = () => ({
    name: 'shopweb-normalize-laravel-manifest',
    apply: 'build',
    closeBundle() {
        const manifestPath = path.join(projectRoot, 'public', 'build', 'manifest.json');

        if (!fs.existsSync(manifestPath)) {
            return;
        }

        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
        const normalized = {};

        for (const [key, entry] of Object.entries(manifest)) {
            const nextEntry = { ...entry };

            if (typeof nextEntry.src === 'string') {
                nextEntry.src = normalizeManifestPath(nextEntry.src);
            }

            normalized[normalizeManifestPath(key)] = nextEntry;
        }

        fs.writeFileSync(manifestPath, `${JSON.stringify(normalized, null, 2)}\n`);
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
        normalizeLaravelManifest(),
    ],
});
