<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BuyerAddress;
use App\Support\GhanaLocations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $addresses = $request->user()
            ->buyerAddresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map->toInertia()
            ->values();

        return Inertia::render('shop/addresses/index', [
            'addresses' => $addresses,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('shop/addresses/form', [
            'address' => null,
            'defaults' => $this->defaultsFromUser($request),
            'returnTo' => $request->query('return'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $user = $request->user();

        $makeDefault = (bool) ($validated['is_default'] ?? false)
            || $user->buyerAddresses()->count() === 0;

        if ($makeDefault) {
            $user->buyerAddresses()->update(['is_default' => false]);
        }

        $user->buyerAddresses()->create([
            ...$validated,
            'is_default' => $makeDefault,
        ]);

        return $this->redirectAfterSave($request, 'Address saved.');
    }

    public function edit(Request $request, BuyerAddress $address): Response
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        return Inertia::render('shop/addresses/form', [
            'address' => $address->toInertia(),
            'defaults' => null,
            'returnTo' => $request->query('return'),
        ]);
    }

    public function update(Request $request, BuyerAddress $address): RedirectResponse
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
            // keep at least one default if this was the only one
            if ($request->user()->buyerAddresses()->where('is_default', true)->doesntExist()) {
                $address->update(['is_default' => true]);
            }
        }

        return $this->redirectAfterSave($request, 'Address updated.');
    }

    public function destroy(Request $request, BuyerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->buyerAddresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Address deleted.');
    }

    public function setDefault(Request $request, BuyerAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $request->user()->buyerAddresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return back()->with('success', 'Default address updated.');
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

    /**
     * @return array<string, string|null>
     */
    private function defaultsFromUser(Request $request): array
    {
        $user = $request->user();
        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        return [
            'first_name' => $user->first_name ?: ($nameParts[0] ?? ''),
            'last_name' => $user->last_name ?: ($nameParts[1] ?? ''),
            'phone' => $user->mobile,
            'secondary_phone' => null,
            'address_line' => $user->residential_address,
            'additional_details' => null,
            'region' => GhanaLocations::isValidRegion((string) $user->region) ? $user->region : '',
            'city' => $user->city,
            'digital_address' => $user->digital_address,
            'is_default' => true,
        ];
    }

    private function redirectAfterSave(Request $request, string $message): RedirectResponse
    {
        if ($request->input('return') === 'checkout' || $request->query('return') === 'checkout') {
            return redirect()->route('checkout.index')->with('success', $message);
        }

        return redirect()->route('addresses.index')->with('success', $message);
    }
}
