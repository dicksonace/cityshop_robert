<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $base = rtrim(config('app.url'), '/');

        $urls = [
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/search', 'changefreq' => 'daily', 'priority' => '0.8'],
            ['loc' => $base.'/faq', 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => $base.'/contact', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ];

        Product::query()
            ->visibleInShop()
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get(['slug', 'updated_at'])
            ->each(function (Product $product) use (&$urls, $base) {
                $urls[] = [
                    'loc' => $base.'/products/'.$product->slug,
                    'lastmod' => optional($product->updated_at)?->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => '0.9',
                ];
            });

        SellerProfile::query()
            ->where('status', 'approved')
            ->whereNotNull('slug')
            ->orderByDesc('updated_at')
            ->limit(2000)
            ->get(['slug', 'updated_at'])
            ->each(function (SellerProfile $profile) use (&$urls, $base) {
                $urls[] = [
                    'loc' => $base.'/store/'.$profile->slug,
                    'lastmod' => optional($profile->updated_at)?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            });

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(): Response
    {
        $body = view('robots')->render();

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
