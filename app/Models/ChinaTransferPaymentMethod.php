<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ChinaTransferPaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'type',
        'account_name',
        'account_number',
        'bank_name',
        'network',
        'instructions',
        'qr_path',
        'proof_required',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'proof_required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function qrUrl(): ?string
    {
        return $this->qr_path ? Storage::disk('public')->url($this->qr_path) : null;
    }
}
