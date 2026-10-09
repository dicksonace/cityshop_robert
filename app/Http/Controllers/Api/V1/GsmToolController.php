<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GsmServiceType;
use App\Http\Controllers\Controller;
use App\Models\GsmOrder;
use App\Models\GsmService;
use App\Models\PlaceOrderSlide;
use App\Services\GsmToolService;
use App\Services\KycService;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GsmToolController extends Controller
{
    public function __construct(private GsmToolService $gsm) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'services' => $this->gsm->activeServices()->map(fn (GsmService $s) => $this->gsm->servicePayload($s))->values(),
            'groups' => $this->gsm->catalogGroups(),
            'service_types' => GsmServiceType::options(),
            'contact_email' => $user->email,
            'wallet' => WalletService::ensure($user)->toFrontendArray(),
            'has_payment_pin' => PaymentPinService::hasPin($user),
            'kyc' => KycService::payload($user, withPhotos: false),
            'slides' => PlaceOrderSlide::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PlaceOrderSlide $slide) => [
                    'id' => $slide->id,
                    'image_url' => $slide->imageUrl(),
                ])
                ->filter(fn (array $slide) => $slide['image_url'] !== '')
                ->values(),
        ]);
    }

    public function indexOrders(Request $request): JsonResponse
    {
        $page = $this->gsm->paginatedBuyerOrders($request->user(), $request);

        return response()->json([
            'data' => $page['data'],
            'meta' => $page['meta'],
        ]);
    }

    public function showService(Request $request, GsmService $gsmService): JsonResponse
    {
        abort_unless($gsmService->active, 404);

        return response()->json([
            'service' => $this->gsm->servicePayload($gsmService->load('activeFields')),
            'wallet' => WalletService::ensure($request->user())->toFrontendArray(),
            'has_payment_pin' => PaymentPinService::hasPin($request->user()),
            'kyc' => KycService::payload($request->user(), withPhotos: false),
            'contact_email' => $request->user()?->email,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($denied = KycService::denyStoreFundsResponse($user)) {
            return $denied;
        }

        $request->validate([
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($user, $request->input('payment_pin'));

        $order = $this->gsm->createOrder($user, $request);

        return response()->json([
            'message' => 'Order placed. Deducted from your wallet.',
            'order' => $this->gsm->orderPayload($order, withHistory: true, buyerView: true),
            'wallet' => WalletService::ensure($user)->toFrontendArray(),
        ], 201);
    }

    public function showOrder(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        abort_unless((int) $gsmOrder->user_id === (int) $request->user()->id, 403);

        return response()->json([
            'order' => $this->gsm->orderPayload($gsmOrder, withHistory: true, buyerView: true),
        ]);
    }

    public function cancel(Request $request, GsmOrder $gsmOrder): JsonResponse
    {
        abort(403, 'Only an admin can cancel this GSM order.');
    }
}
