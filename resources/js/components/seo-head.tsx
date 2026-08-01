import { Head, usePage } from '@inertiajs/react';

import { SharedData } from '@/types';

type SeoHeadProps = {
    title: string;
    description?: string | null;
    image?: string | null;
    url?: string | null;
    type?: 'website' | 'product' | 'profile';
    noindex?: boolean;
    jsonLd?: Record<string, unknown> | Record<string, unknown>[] | null;
    keywords?: string | null;
};

function absoluteUrl(pathOrUrl: string | null | undefined, appUrl: string): string | undefined {
    if (!pathOrUrl) return undefined;
    if (pathOrUrl.startsWith('http://') || pathOrUrl.startsWith('https://')) return pathOrUrl;
    const base = appUrl.replace(/\/$/, '');
    const path = pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`;
    return `${base}${path}`;
}

export default function SeoHead({
    title,
    description,
    image,
    url,
    type = 'website',
    noindex = false,
    jsonLd = null,
    keywords = null,
}: SeoHeadProps) {
    const { name, appUrl, seo } = usePage<
        SharedData & {
            appUrl?: string;
            seo?: { defaultDescription?: string; defaultImage?: string };
        }
    >().props;

    const site = name || 'CityShop';
    const base = appUrl || (typeof window !== 'undefined' ? window.location.origin : '');
    const desc =
        (description && description.trim()) ||
        seo?.defaultDescription ||
        'Shop products from local Ghana sellers on CityShop.';
    const canonical = absoluteUrl(url, base) || (typeof window !== 'undefined' ? window.location.href : base);
    const ogImage = absoluteUrl(image, base) || absoluteUrl(seo?.defaultImage || '/images/logo.png', base);
    const fullTitle = title.includes(site) ? title : `${title} · ${site}`;

    const scripts = jsonLd
        ? (Array.isArray(jsonLd) ? jsonLd : [jsonLd]).map((block, i) => (
              <script key={i} type="application/ld+json">
                  {JSON.stringify(block)}
              </script>
          ))
        : null;

    return (
        <Head title={title}>
            <meta head-key="description" name="description" content={desc.slice(0, 320)} />
            {keywords && <meta head-key="keywords" name="keywords" content={keywords} />}
            {noindex ? (
                <meta head-key="robots" name="robots" content="noindex,nofollow" />
            ) : (
                <meta head-key="robots" name="robots" content="index,follow" />
            )}
            {canonical && <link head-key="canonical" rel="canonical" href={canonical} />}

            <meta head-key="og:type" property="og:type" content={type} />
            <meta head-key="og:site_name" property="og:site_name" content={site} />
            <meta head-key="og:title" property="og:title" content={fullTitle} />
            <meta head-key="og:description" property="og:description" content={desc.slice(0, 320)} />
            {canonical && <meta head-key="og:url" property="og:url" content={canonical} />}
            {ogImage && <meta head-key="og:image" property="og:image" content={ogImage} />}

            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
            <meta head-key="twitter:title" name="twitter:title" content={fullTitle} />
            <meta head-key="twitter:description" name="twitter:description" content={desc.slice(0, 320)} />
            {ogImage && <meta head-key="twitter:image" name="twitter:image" content={ogImage} />}

            {scripts}
        </Head>
    );
}
