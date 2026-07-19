<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuyerAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'secondary_phone',
        'address_line',
        'additional_details',
        'region',
        'city',
        'digital_address',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Shipping payload expected by OrderService::createCheckoutFromCart.
     *
     * @return array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address: ?string, delivery_notes: ?string}
     */
    public function toShippingArray(): array
    {
        $notes = [];
        if (filled($this->address_line)) {
            $notes[] = $this->address_line;
        }
        if (filled($this->additional_details)) {
            $notes[] = $this->additional_details;
        }
        if (filled($this->secondary_phone)) {
            $notes[] = 'Alt phone: '.$this->secondary_phone;
        }

        return [
            'receiver_name' => $this->fullName(),
            'receiver_phone' => $this->phone,
            'region' => $this->region,
            'city' => $this->city,
            'digital_address' => $this->digital_address,
            'delivery_notes' => $notes === [] ? null : implode("\n", $notes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertia(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'address_line' => $this->address_line,
            'additional_details' => $this->additional_details,
            'region' => $this->region,
            'city' => $this->city,
            'digital_address' => $this->digital_address,
            'is_default' => $this->is_default,
        ];
    }
}
