<?php

namespace App\Http\Requests\Admin;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSellerInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_business_registered' => $this->boolean('is_business_registered'),
            'accept_marketplace_payments' => $this->boolean('accept_marketplace_payments'),
            'accept_direct_payments' => $this->boolean('accept_direct_payments'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $seller = $this->route('seller');
        abort_unless($seller instanceof SellerProfile, 404);
        $seller->loadMissing('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($seller->user->id),
            ],
            'mobile' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class)->ignore($seller->user->id),
            ],
            'ghana_card_number' => ['nullable', 'string', 'max:40'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'residential_address' => ['nullable', 'string', 'max:500'],
            'store_name' => ['required', 'string', 'max:255'],
            'is_business_registered' => ['required', 'boolean'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'accept_marketplace_payments' => ['required', 'boolean'],
            'accept_direct_payments' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->boolean('accept_marketplace_payments') && ! $this->boolean('accept_direct_payments')) {
                $validator->errors()->add('accept_marketplace_payments', 'Choose at least one buyer payment mode.');
            }
        });
    }
}
