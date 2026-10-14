<?php

namespace App\Support;

use App\Contracts\HostedPaymentGateway;
use App\Services\FlutterwaveService;
use App\Services\JunipayService;
use App\Services\KorapayService;
use Illuminate\Http\Request;

/**
 * Korapay routes reuse the Flutterwave controller actions with ->defaults('gateway', 'korapay').
 */
class HostedGateways
{
    public static function named(?string $name): HostedPaymentGateway
    {
        return match ($name) {
            'korapay' => app(KorapayService::class),
            'junipay' => app(JunipayService::class),
            default => app(FlutterwaveService::class),
        };
    }

    public static function fromRequest(Request $request): HostedPaymentGateway
    {
        $name = $request->route('gateway') ?? $request->input('gateway');

        return self::named(is_string($name) ? $name : null);
    }
}
