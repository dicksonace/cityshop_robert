<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $category = Category::query()->where('slug', Category::GSM_TOOLS_SLUG)->first();
        if (! $category) {
            return;
        }

        DB::table('products')->where('category_id', $category->id)->update(['category_id' => null]);
        $category->update(['is_active' => false]);
    }

    public function down(): void
    {
        Category::query()->where('slug', Category::GSM_TOOLS_SLUG)->update(['is_active' => true]);
    }
};
