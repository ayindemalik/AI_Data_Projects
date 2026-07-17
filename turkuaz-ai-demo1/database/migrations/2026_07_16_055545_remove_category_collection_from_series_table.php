<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Series (mapped from the legacy product_collection table, e.g. "İbiza", "Sharp")
     * turns out NOT to belong to a single category in the real data — the same
     * series spans multiple product categories. So we drop the FK entirely here;
     * Products (built next) will hold category_id, subcategory_id, and series_id
     * independently, matching the legacy products table's plain integer columns.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('series', 'category_id') && !Schema::hasColumn('series', 'collection_id')) {
            return;
        }

        Schema::table('series', function (Blueprint $table) {
            if (Schema::hasColumn('series', 'category_id')) {
                $table->dropForeign(['category_id']);
            }
            if (Schema::hasColumn('series', 'collection_id')) {
                $table->dropForeign(['collection_id']);
            }
            $table->dropColumn(array_filter(
                ['category_id', 'collection_id'],
                fn ($col) => Schema::hasColumn('series', $col)
            ));
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }
};
