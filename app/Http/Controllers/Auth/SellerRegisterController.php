<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\SellerRegistrationInvite;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SellerRegistrationInviteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SellerRegisterController extends Controller
{
    public function redirectToContact(): RedirectResponse
    {
        return redirect()->route('contact')->with(
            'info',
            'Seller registration is by invitation only. Contact support to request a seller account — we will send you a private registration link if approved.',
        );
    }

    public function create(Request $request, string $token, SellerRegistrationInviteService $invites): Response|RedirectResponse
    {
        $invite = $invites->findValidByToken($token);

        if (! $invite) {
            return redirect()->route('contact')->with(
                'error',
                'This registration link is invalid, expired, or has already been used. Please contact support for assistance.',
            );
        }

        $user = $request->user();

        if ($user) {
            $profile = $user->sellerProfile;

            if ($user->isSeller() && $profile?->status === SellerStatus::Pending) {
                return redirect()->route('seller.login')->with(
                    'status',
                    'Your seller application is under review. You can sign in at Seller Centre after admin approval.',
                );
            }

            $gateReason = $this->inviteGateReason($user, $invite);

            if ($gateReason !== null) {
                return Inertia::render('auth/seller-invite-gate', [
                    'token' => $token,
                    'reason' => $gateReason,
                    'inviteUrl' => route('register.seller', ['token' => $token], absolute: true),
                    'inviteEmail' => $invite->email,
                    'inviteName' => $invite->name,
                    'expiresAt' => $invite->expires_at->toIso8601String(),
                    'currentUser' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                    ],
                ]);
            }
        }

        $prefillEmail = $invite->email ?? $user?->email ?? '';
        $inviteFirstName = $invite->name ? explode(' ', $invite->name, 2)[0] : ($user?->first_name ?? '');
        $inviteLastName = $invite->name
            ? (explode(' ', $invite->name, 2)[1] ?? '')
            : ($user?->last_name ?? '');

        return Inertia::render('auth/seller-register', [
            'token' => $token,
            'expiresAt' => $invite->expires_at->toIso8601String(),
            'isExistingUser' => (bool) $user,
            'defaults' => [
                'first_name' => old('first_name', $inviteFirstName),
                'last_name' => old('last_name', $inviteLastName),
                'mobile' => old('mobile', $user?->mobile ?? ''),
                'whatsapp' => old('whatsapp', $user?->whatsapp ?? ''),
                'email' => old('email', $prefillEmail),
                'ghana_card_number' => old('ghana_card_number', $user?->ghana_card_number ?? ''),
                'digital_address' => old('digital_address', $user?->digital_address ?? ''),
                'residential_address' => old('residential_address', $user?->residential_address ?? ''),
                'region' => old('region', $user?->region ?? ''),
                'city' => old('city', $user?->city ?? ''),
                'store_name' => old('store_name', ''),
                'business_name' => old('business_name', ''),
                'business_registration_number' => old('business_registration_number', ''),
                'business_address' => old('business_address', ''),
                'tin' => old('tin', ''),
                'is_business_registered' => old('is_business_registered', '0') === '1'
                    || old('is_business_registered') === true
                    || old('is_business_registered') === 'true',
            ],
        ]);
    }

    public function store(Request $request, string $token, SellerRegistrationInviteService $invites): RedirectResponse
    {
        if (
            $request->isMethod('post')
            && empty($request->all())
            && empty($request->allFiles())
            && (int) $request->server('CONTENT_LENGTH', 0) > 0
        ) {
            return back()->with(
                'error',
                'Upload too large for the server. Use smaller images (under 5MB each) and try again.',
            );
        }

        $invite = $invites->findValidByToken($token);

        if (! $invite) {
            return redirect()->route('contact')->with(
                'error',
                'This registration link is invalid, expired, or has already been used. Please contact support for assistance.',
            );
        }

        $existingUser = $request->user();
        $isRegistered = $request->boolean('is_business_registered');

        if ($existingUser) {
            if ($existingUser->isAdmin()) {
                abort(403);
            }

            if ($invite->email && strcasecmp($existingUser->email, $invite->email) !== 0) {
                return back()->with('error', 'This registration link was issued for a different email address.');
            }

            $profile = $existingUser->sellerProfile;
            if ($existingUser->isSeller() && $profile) {
                if ($profile->status === SellerStatus::Approved) {
                    return redirect()->route('seller.dashboard');
                }
                if ($profile->status === SellerStatus::Pending) {
                    return redirect()->route('seller.login')->with(
                        'status',
                        'Your seller application is under review. You can sign in after admin approval.',
                    );
                }
                if ($profile->status === SellerStatus::Suspended) {
                    return back()->with('error', 'Your seller account is suspended. Please contact support.');
                }
            }
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mobile' => ['required', 'string', 'max:20', Rule::unique('users', 'mobile')->ignore($existingUser?->id)],
            'whatsapp' => ['required', 'string', 'max:20'],
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

        if ($invite->email) {
            $rules['email'][] = Rule::in([strtolower($invite->email)]);
        }

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

        $validator = Validator::make($request->all(), $rules, [
            'id_card_front.required' => 'Please upload the front of your Ghana Card.',
            'id_card_back.required' => 'Please upload the back of your Ghana Card.',
            'shop_photo.required' => 'Please upload a photo of the front of your shop.',
            'form_a.required' => 'Please upload Form A for your registered business.',
            'form_b.required' => 'Please upload Form B for your registered business.',
            'business_certificate.required' => 'Please upload your business certificate.',
        ]);

        if ($validator->fails()) {
            $count = $validator->errors()->count();

            return back()
                ->withErrors($validator)
                ->with(
                    'error',
                    $count === 1
                        ? 'Please fix 1 required field and submit again.'
                        : "Please fix {$count} required fields and submit again.",
                )
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                    'id_card_front',
                    'id_card_back',
                    'shop_photo',
                    'form_a',
                    'form_b',
                    'business_certificate',
                ]));
        }

        $validated = $validator->validated();

        $applicant = null;

        try {
            if ($existingUser) {
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
                $sellerProfile = $user->sellerProfile->fresh();
            } else {
                $sellerProfile = SellerProfile::create([
                    'user_id' => $user->id,
                    ...$profileData,
                ]);
            }

            if (! $user->wallet) {
                Wallet::create(['user_id' => $user->id]);
            }

            $invites->markUsed($invite, $sellerProfile);

            if (! $existingUser) {
                event(new Registered($user));
            }

            $applicant = [
                'name' => $user->name,
                'email' => $user->email,
                'submitted_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with(
                    'error',
                    'We could not save your application. Check that document photos are under 5MB each, then try again.',
                )
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                    'id_card_front',
                    'id_card_back',
                    'shop_photo',
                    'form_a',
                    'form_b',
                    'business_certificate',
                ]));
        }

        Auth::guard('web')->logout();

        return redirect()
            ->route('seller.application.submitted')
            ->with('success', 'Your seller application was submitted successfully. Our team will review it within 24–48 hours.')
            ->with('seller_application', $applicant);
    }

    public function applicationSubmitted(Request $request): Response|RedirectResponse
    {
        $applicant = $request->session()->get('seller_application');

        if (! is_array($applicant) || empty($applicant['email'])) {
            return redirect()->route('seller.login')->with(
                'status',
                'Sign in at Seller Centre after your application has been approved.',
            );
        }

        return Inertia::render('auth/seller-application-submitted', [
            'applicant' => [
                'name' => $applicant['name'] ?? 'Seller',
                'email' => $applicant['email'],
            ],
            'submittedAt' => $applicant['submitted_at'] ?? null,
        ]);
    }

    /**
     * @return 'admin_signed_in'|'wrong_account'|'already_approved'|'suspended'|null
     */
    private function inviteGateReason(User $user, SellerRegistrationInvite $invite): ?string
    {
        if ($user->isAdmin()) {
            return 'admin_signed_in';
        }

        if ($invite->email && strcasecmp($user->email, $invite->email) !== 0) {
            return 'wrong_account';
        }

        $profile = $user->sellerProfile;
        if ($user->isSeller() && $profile) {
            if ($profile->status === SellerStatus::Approved) {
                return 'already_approved';
            }
            if ($profile->status === SellerStatus::Suspended) {
                return 'suspended';
            }
        }

        return null;
    }
}
