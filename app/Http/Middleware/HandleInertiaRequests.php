<?php

namespace App\Http\Middleware;

use App\Services\ChatService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $cartCount = 0;
        $wishlistProductIds = [];
        $wishlistCount = 0;

        if ($user) {
            $cartCount = \App\Models\CartItem::where('user_id', $user->id)->sum('quantity');
            $wishlistProductIds = \App\Models\Wishlist::where('user_id', $user->id)->pluck('product_id')->all();
            $wishlistCount = count($wishlistProductIds);
        }

        $unreadMessages = $user ? ChatService::unreadMessageCount($user) : 0;
        $unreadNotifications = $user ? ChatService::unreadNotificationCount($user) : 0;

        return [
            ...parent::share($request),
            'csrfToken' => csrf_token(),
            'name' => config('app.name', 'CityShop'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? $user->load('sellerProfile') : null,
            ],
            'cartCount' => $cartCount,
            'wishlistProductIds' => $wishlistProductIds,
            'wishlistCount' => $wishlistCount,
            'unreadMessages' => $unreadMessages,
            'unreadNotifications' => $unreadNotifications,
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'openChat' => fn () => $request->session()->get('openChat'),
            ],
        ];
    }
}
