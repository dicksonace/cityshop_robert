import postcss from 'postcss';
import oklabFunction from '@csstools/postcss-oklab-function';

/**
 * Convert oklch/oklab colors to sRGB so Safari 15 (iPhone 6s/7, iOS 15)
 * can render Tailwind v4 theme colors. Without this, utilities like
 * bg-orange-500 silently fail and the shop looks black-and-white.
 */
export function safariCssCompat() {
    return {
        name: 'safari-css-compat',
        apply: 'build',
        enforce: 'post',
        async generateBundle(_options, bundle) {
            const processor = postcss([
                oklabFunction({
                    preserve: false,
                    subFeatures: {
                        displayP3: false,
                    },
                }),
            ]);

            for (const [fileName, chunk] of Object.entries(bundle)) {
                if (!fileName.endsWith('.css') || chunk.type !== 'asset') {
                    continue;
                }

                const source = typeof chunk.source === 'string'
                    ? chunk.source
                    : Buffer.from(chunk.source).toString('utf8');

                const result = await processor.process(source, { from: undefined });
                chunk.source = result.css;
            }
        },
    };
}
