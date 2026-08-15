<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Resolve any cross-seller duplicates before adding a global unique index.
        // Keep the oldest row on the original slug; renumber the rest.
        $duplicateSlugs = DB::table('products')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicateSlugs as $slug) {
            $products = Product::withTrashed()
                ->where('slug', $slug)
                ->orderBy('id')
                ->get();

            foreach ($products as $index => $product) {
                if ($index === 0) {
                    continue;
                }

                $base = Str::slug((string) $product->name);
                if ($base === '') {
                    $base = 'product';
                }

                $n = 1;
                do {
                    $candidate = "{$base}-{$n}";
                    $n++;
                } while (
                    Product::withTrashed()->where('slug', $candidate)->exists()
                );

                $product->forceFill(['slug' => $candidate])->saveQuietly();
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['seller_id', 'slug']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['seller_id', 'slug']);
        });
    }
};
