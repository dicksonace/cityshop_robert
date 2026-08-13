<?php

namespace App\Http\Requests\Admin;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->targetUser();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($target->id),
            ],
            'mobile' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class)->ignore($target->id),
            ],
        ];
    }

    public function targetUser(): User
    {
        $seller = $this->route('seller');
        if ($seller instanceof SellerProfile) {
            $seller->loadMissing('user');

            return $seller->user;
        }

        $buyer = $this->route('buyer');
        if ($buyer instanceof User) {
            return $buyer;
        }

        abort(404);
    }
}
