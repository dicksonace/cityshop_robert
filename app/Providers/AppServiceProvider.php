<?php

namespace App\Providers;

use App\Models\ProductImage;
use App\Observers\ProductImageObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductImage::observe(ProductImageObserver::class);

        // In production the site is always served over HTTPS (behind an SSL proxy),
        // so force every generated URL — shared store links, seller invite links,
        // and emailed links — to use https instead of falling back to http.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');

            $appUrl = (string) config('app.url');
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceRootUrl($appUrl);
            }
        }
    }
}
