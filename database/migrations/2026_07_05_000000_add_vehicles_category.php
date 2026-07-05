<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $config = config('category_specs.vehicles');

        if (! $config) {
            return;
        }

        Category::updateOrCreate(
            ['slug' => 'vehicles'],
            [
                'name' => 'Vehicles',
                'icon' => $config['icon'],
                'spec_schema' => ['fields' => $config['fields']],
                'is_active' => true,
                'sort_order' => 9,
            ]
        );
    }

    public function down(): void
    {
        Category::where('slug', 'vehicles')->delete();
    }
};
