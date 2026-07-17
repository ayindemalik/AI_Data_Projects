<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measures', function (Blueprint $table) {
            $table->id();
            $table->json('name');            // e.g. {"tr": "Genişlik", "en": "Width"}
            $table->string('unit');          // e.g. "cm", "kg" — free text, kept simple
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        // NOTE: the product_measures pivot (linking a Product to a Measure with a numeric
        // value) is created in the Products slice, once the products table exists.
    }

    public function down(): void
    {
        Schema::dropIfExists('measures');
    }
};
