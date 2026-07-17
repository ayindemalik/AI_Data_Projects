<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Plain columns, no DB-level FK constraint — mirrors the legacy
            // products table's category/sub_category/collection integer columns,
            // and lets legacy product rows be imported directly with zero remapping.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->unsignedBigInteger('series_id')->nullable();

            $table->string('sku')->nullable()->unique(); // Main professional/dealer product code
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('dimensions')->nullable(); // Display text, e.g. "91x51 cm"
            $table->string('status')->default('active'); // active | inactive

            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
