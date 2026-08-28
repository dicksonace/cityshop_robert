<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SellRmbReceiveMethod extends Model
{
    protected $fillable = [
        'name',
        'type',
        'account_name',
        'account_number',
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
        if (! $this->qr_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->qr_path);
        $version = $this->updated_at?->timestamp ?? time();

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
    }
}
