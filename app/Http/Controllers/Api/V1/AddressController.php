<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Support\GhanaLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->buyerAddresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map->toInertia()
            ->values();

        return response()->json([
            'data' => $addresses,
            'regions' => GhanaLocations::regions(),
            'cities_by_region' => GhanaLocations::citiesByRegion(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $user = $request->user();

        $makeDefault = (bool) ($validated['is_default'] ?? false)
            || $user->buyerAddresses()->count() === 0;

        if ($makeDefault) {
            $user->buyerAddresses()->update(['is_default' => false]);
        }

        $address = $user->buyerAddresses()->create([
            ...$validated,
            'is_default' => $makeDefault,
        ]);

        return response()->json([
            'message' => 'Address saved.',
            'data' => $address->toInertia(),
        ], 201);
    }

    public function update(Request $request, BuyerAddress $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $validated = $this->validated($request);
        $makeDefault = (bool) ($validated['is_default'] ?? false);

        if ($makeDefault) {
            $request->user()->buyerAddresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $address->update([
            ...$validated,
            'is_default' => $makeDefault || $address->is_default,
        ]);

        if (! $makeDefault && $address->fresh()->is_default === false) {
            if ($request->user()->buyerAddresses()->where('is_default', true)->doesntExist()) {
                $address->update(['is_default' => true]);
            }
        }

        return response()->json([
            'message' => 'Address updated.',
            'data' => $address->fresh()->toInertia(),
        ]);
    }

    public function destroy(Request $request, BuyerAddress $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->buyerAddresses()->latest()->first()?->update(['is_default' => true]);
        }

        return $this->index($request);
    }

    public function setDefault(Request $request, BuyerAddress $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $request->user()->buyerAddresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address updated.',
            'data' => $address->fresh()->toInertia(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'secondary_phone' => ['nullable', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:255'],
            'additional_details' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100', Rule::in(GhanaLocations::regions())],
            'city' => ['required', 'string', 'max:100'],
            'digital_address' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }
}
