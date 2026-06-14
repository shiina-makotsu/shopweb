import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

const normalizeManifestPath = (value) => {
    const normalized = value.replace(/\\/g, '/').replace(/^\/([A-Za-z]:\/)/, '$1');
    const root = projectRoot.replace(/\\/g, '/').replace(/^\/([A-Za-z]:\/)/, '$1');
    const normalizedLower = normalized.toLowerCase();
    const rootLower = root.toLowerCase();

    return normalizedLower.startsWith(`${rootLower}/`)
        ? normalized.slice(root.length + 1)
        : normalized;
};

const normalizeManifest = (manifest) => {
    const normalized = {};

    for (const [key, entry] of Object.entries(manifest)) {
        const nextEntry = { ...entry };

        if (typeof nextEntry.src === 'string') {
            nextEntry.src = normalizeManifestPath(nextEntry.src);
        }

        normalized[normalizeManifestPath(key)] = nextEntry;
    }

    return normalized;
};

const normalizeManifestFile = (manifestPath) => {
    try {
        if (!fs.existsSync(manifestPath)) {
            return;
        }

        const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

        fs.writeFileSync(manifestPath, `${JSON.stringify(normalizeManifest(manifest), null, 2)}\n`);
    } catch (error) {
        console.warn(`Unable to normalize Vite manifest at ${manifestPath}:`, error);
    }
};

const normalizeLaravelManifest = () => ({
    name: 'shopweb-normalize-laravel-manifest',
    apply: 'build',
    enforce: 'post',
    generateBundle(_, bundle) {
        for (const asset of Object.values(bundle)) {
            if (asset.type !== 'asset' || !asset.fileName.endsWith('manifest.json') || typeof asset.source !== 'string') {
                continue;
            }

            try {
                asset.source = `${JSON.stringify(normalizeManifest(JSON.parse(asset.source)), null, 2)}\n`;
            } catch (error) {
                console.warn(`Unable to normalize Vite manifest asset ${asset.fileName}:`, error);
            }
        }
    },
    writeBundle() {
        normalizeManifestFile(path.join(projectRoot, 'public', 'build', 'manifest.json'));
    },
    closeBundle() {
        normalizeManifestFile(path.join(projectRoot, 'public', 'build', 'manifest.json'));
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
