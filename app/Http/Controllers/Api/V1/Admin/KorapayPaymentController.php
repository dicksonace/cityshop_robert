<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\KorapayPaymentController as WebKorapayPayments;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KorapayWebhookController;
use App\Models\KorapayPayment;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KorapayPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payments = WebKorapayPayments::paginate($request, min(max((int) $request->integer('per_page', 20), 1), 50));
        $korapay = PlatformSettings::korapayKeysStatus();
        $totals = WebKorapayPayments::totals();

        return response()->json([
            'data' => $payments->getCollection()->values(),
            'meta' => AdminJson::meta($payments),
            'dashboard' => [
                'paid_total' => $totals['success'],
                'paid_count' => $totals['count'],
                'korapay_configured' => (bool) ($korapay['configured'] ?? false),
                'korapay_available' => (bool) ($korapay['available'] ?? false),
                'korapay_is_test' => (bool) ($korapay['is_test'] ?? false),
            ],
        ]);
    }

    public function recheck(KorapayPayment $korapayPayment, KorapayWebhookController $settler): JsonResponse
    {
        [$ok, $message] = WebKorapayPayments::settle($korapayPayment, $settler);

        return response()->json([
            'message' => $message,
            'status' => $korapayPayment->fresh()?->status,
        ], $ok ? 200 : 422);
    }
}
