<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChinaTransferFormField extends Model
{
    public const TYPES = [
        'text',
        'number',
        'phone',
        'email',
        'dropdown',
        'radio',
        'checkbox',
        'date',
        'textarea',
        'image',
        'document',
        'files',
    ];

    protected $fillable = [
        'group',
        'type',
        'name',
        'label',
        'placeholder',
        'help_text',
        'required',
        'options',
        'file_types',
        'max_size_kb',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
            'options' => 'array',
            'file_types' => 'array',
        ];
    }

    public function isFile(): bool
    {
        return in_array($this->type, ['image', 'document', 'files'], true);
    }
}
