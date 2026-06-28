<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasColumn('products', 'meta_keywords')) {
            return;
        }

        $existing = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_search_fulltext'");

        if (count($existing) > 0) {
            return;
        }

        DB::statement(
            'ALTER TABLE products ADD FULLTEXT products_search_fulltext (name, brand, description, meta_keywords)'
        );
    }

    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $existing = DB::select("SHOW INDEX FROM products WHERE Key_name = 'products_search_fulltext'");

        if (count($existing) === 0) {
            return;
        }

        DB::statement('ALTER TABLE products DROP INDEX products_search_fulltext');
    }
};
