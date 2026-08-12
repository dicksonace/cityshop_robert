<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Support\SharePreview;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AppLinkController extends Controller
{
    public function product(string $slug): View
    {
        $product = Product::with('images')
            ->visibleInShop()
            ->where('slug', $slug)
            ->firstOrFail();

        $share = SharePreview::productPreview($product->toArray());
        $webUrl = url('/products/'.$slug);

        return $this->landing(
            share: $share,
            webUrl: $webUrl,
            appUrl: 'cityshop://products/'.$slug,
            heading: $product->name,
            kind: 'product',
        );
    }

    public function store(string $slug): View
    {
        $store = SellerProfile::query()
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $share = SharePreview::storePreview($store->toArray());
        $webUrl = url('/store/'.$slug);

        return $this->landing(
            share: $share,
            webUrl: $webUrl,
            appUrl: 'cityshop://stores/'.$slug,
            heading: $store->displayName(),
            kind: 'store',
        );
    }

    public function live(string $slug): View
    {
        $store = SellerProfile::query()
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $share = SharePreview::storePreview($store->toArray());
        $share['title'] = $store->displayName().' is live · CityShop';
        $webUrl = url('/store/'.$slug);

        return $this->landing(
            share: $share,
            webUrl: $webUrl,
            appUrl: 'cityshop://live/'.$slug,
            heading: $store->displayName().' live',
            kind: 'live',
        );
    }

    public function assetLinks(): JsonResponse
    {
        $fingerprints = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_APP_SHA256', '0E:35:E3:DB:D3:D7:09:40:8A:DD:0B:1B:0F:E3:04:92:FD:7F:81:AC:DD:D5:EC:0E:2E:BB:F7:3E:19:88:D8:95')),
        )));

        $body = [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.cityshop.cityshop_mobile',
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]];

        return response()
            ->json($body)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @param  array<string, string>  $share
     */
    private function landing(array $share, string $webUrl, string $appUrl, string $heading, string $kind): View
    {
        $path = parse_url($appUrl, PHP_URL_HOST).parse_url($appUrl, PHP_URL_PATH);
        $intent = 'intent://'.$path.'#Intent;scheme=cityshop;package=com.cityshop.cityshop_mobile;S.browser_fallback_url='.rawurlencode($webUrl).';end';

        return view('app-link', [
            'share' => $share,
            'webUrl' => $webUrl,
            'appUrl' => $appUrl,
            'androidIntent' => $intent,
            'heading' => $heading,
            'kind' => $kind,
        ]);
    }
}
