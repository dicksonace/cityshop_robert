<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $config = config('category_specs.general-goods');

        Category::updateOrCreate(
            ['slug' => 'general-goods'],
            [
                'name' => 'General Goods',
                'icon' => $config['icon'] ?? '📦',
                'spec_schema' => $config ? ['fields' => $config['fields']] : null,
                'is_active' => true,
                'sort_order' => 22,
            ]
        );
    }

    public function down(): void
    {
        Category::where('slug', 'general-goods')->delete();
    }
};
