<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PlaceOrderSlide extends Model
{
    protected $fillable = [
        'image',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function imageUrl(): string
    {
        $image = (string) $this->image;
        if ($image === '') {
            return '';
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }
        if (str_starts_with($image, 'slider/')) {
            return '/'.$image;
        }

        return Storage::disk('public')->url($image);
    }
}
