import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';
import type { Plugin } from 'vite-plus';

/**
 * Serves the app from a subfolder (staging runs it at /hse). Pages and the Wayfinder
 * routes use root-relative literals like '/login', so they need the folder in front.
 * Set APP_PATH_PREFIX at build time (APP_PATH_PREFIX=hse npm run build); unset, this does nothing.
 *
 * ponytail: rewrites every string literal in resources/js starting with "/" plus a letter.
 * If a non-URL string ever starts that way, switch it to a Wayfinder route.
 */
function basePath(): Plugin {
    const prefix = (process.env.APP_PATH_PREFIX ?? '').replace(/^\/|\/$/g, '');

    return {
        name: 'hse:base-path',
        enforce: 'pre',
        // Lazy-loaded chunks are fetched from here; the Laravel plugin would otherwise
        // derive it from ASSET_URL, which the CI build doesn't have.
        config: () => (prefix === '' ? {} : { base: `/${prefix}/build/` }),
        transform(code, id) {
            if (prefix === '' || !/resources[\\/]js[\\/].*\.tsx?$/.test(id)) {
                return null;
            }

            // The lookahead keeps an already prefixed URL from gaining a second.
            return code.replace(
                new RegExp(`(['"\`])/(?!${prefix}[/'"\`?])(?=[a-z])`, 'g'),
                `$1/${prefix}/`,
            );
        },
    };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        basePath(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
