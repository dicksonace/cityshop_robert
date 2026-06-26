<?php

namespace App\Models;

use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'checkout_id',
        'order_id',
        'user_id',
        'type',
        'line_items',
        'subtotal',
        'commission_amount',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'issued_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'line_items' => 'array',
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public static function generateInvoiceNumber(): string
    {
        return 'INV'.date('Ymd').strtoupper(substr(uniqid(), -6));
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
