<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SellerRegisterController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            $profile = $user->sellerProfile;
            if ($user->isSeller() && $profile) {
                if ($profile->status === SellerStatus::Approved) {
                    return redirect()->route('seller.dashboard');
                }
                if ($profile->status === SellerStatus::Pending) {
                    return redirect()->route('seller.pending');
                }
            }
        }

        return Inertia::render('auth/seller-register', [
            'isExistingUser' => (bool) $user,
            'defaults' => [
                'first_name' => $user?->first_name ?? '',
                'last_name' => $user?->last_name ?? '',
                'mobile' => $user?->mobile ?? '',
                'whatsapp' => $user?->whatsapp ?? '',
                'email' => $user?->email ?? '',
                'ghana_card_number' => $user?->ghana_card_number ?? '',
                'digital_address' => $user?->digital_address ?? '',
                'residential_address' => $user?->residential_address ?? '',
                'region' => $user?->region ?? '',
                'city' => $user?->city ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $existingUser = $request->user();
        $isRegistered = $request->boolean('is_business_registered');

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($existingUser?->id)],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingUser?->id)],
            'ghana_card_number' => ['required', 'string', 'max:50'],
            'digital_address' => ['required', 'string', 'max:100'],
            'residential_address' => ['required', 'string', 'max:500'],
            'region' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'is_business_registered' => ['required', 'boolean'],
            'id_card_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'id_card_back' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'shop_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];

        if (! $existingUser) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        if ($isRegistered) {
            $rules = array_merge($rules, [
                'business_name' => ['required', 'string', 'max:255'],
                'business_registration_number' => ['required', 'string', 'max:100'],
                'business_address' => ['required', 'string', 'max:500'],
                'tin' => ['nullable', 'string', 'max:50'],
                'form_a' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'form_b' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
                'business_certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
        } else {
            $rules['store_name'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        if ($existingUser) {
            if ($existingUser->isAdmin()) {
                abort(403);
            }

            $existingUser->update([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'email' => $validated['email'],
                'ghana_card_number' => $validated['ghana_card_number'],
                'digital_address' => $validated['digital_address'],
                'residential_address' => $validated['residential_address'],
                'region' => $validated['region'],
                'city' => $validated['city'],
                'role' => UserRole::Seller,
            ]);

            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'mobile' => $validated['mobile'],
                'whatsapp' => $validated['whatsapp'] ?? null,
                'email' => $validated['email'],
                'ghana_card_number' => $validated['ghana_card_number'],
                'digital_address' => $validated['digital_address'],
                'residential_address' => $validated['residential_address'],
                'region' => $validated['region'],
                'city' => $validated['city'],
                'password' => Hash::make($validated['password']),
                'role' => UserRole::Seller,
            ]);
        }

        $profileData = [
            'is_business_registered' => $isRegistered,
            'status' => SellerStatus::Pending,
            'rejection_reason' => null,
            'shop_photo' => $request->file('shop_photo')->store('sellers/documents', 'public'),
            'id_card_front' => $request->file('id_card_front')->store('sellers/documents', 'public'),
            'id_card_back' => $request->file('id_card_back')->store('sellers/documents', 'public'),
        ];

        if ($isRegistered) {
            $profileData = array_merge($profileData, [
                'business_name' => $validated['business_name'],
                'business_registration_number' => $validated['business_registration_number'],
                'business_address' => $validated['business_address'],
                'tin' => $validated['tin'] ?? null,
                'form_a' => $request->file('form_a')->store('sellers/documents', 'public'),
                'form_b' => $request->file('form_b')->store('sellers/documents', 'public'),
                'business_certificate' => $request->file('business_certificate')->store('sellers/documents', 'public'),
                'store_name' => null,
            ]);
        } else {
            $profileData['store_name'] = $validated['store_name'];
            $profileData['business_name'] = null;
        }

        if ($user->sellerProfile) {
            $user->sellerProfile->update($profileData);
        } else {
            SellerProfile::create([
                'user_id' => $user->id,
                ...$profileData,
            ]);
        }

        if (! $user->wallet) {
            Wallet::create(['user_id' => $user->id]);
        }

        if (! $existingUser) {
            event(new Registered($user));
            Auth::login($user);
        }

        return redirect()->route('seller.pending')
            ->with('success', 'Your seller application has been submitted. We will review it shortly.');
    }
}
