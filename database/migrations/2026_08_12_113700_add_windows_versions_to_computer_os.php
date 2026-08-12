<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $config = config('category_specs.computers');
        if (! $config) {
            return;
        }

        Category::query()
            ->where('slug', 'computers')
            ->update([
                'spec_schema' => ['fields' => $config['fields']],
            ]);
    }

    public function down(): void
    {
        $category = Category::query()->where('slug', 'computers')->first();
        if (! $category) {
            return;
        }

        $schema = $category->spec_schema ?? ['fields' => []];
        $fields = $schema['fields'] ?? [];

        foreach ($fields as $index => $field) {
            if (($field['key'] ?? null) !== 'os') {
                continue;
            }

            $fields[$index]['options'] = ['Windows 11', 'macOS', 'Linux', 'Chrome OS'];
        }

        $category->update(['spec_schema' => ['fields' => $fields]]);
    }
};
