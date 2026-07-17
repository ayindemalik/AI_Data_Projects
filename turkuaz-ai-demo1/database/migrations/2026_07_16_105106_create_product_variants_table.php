<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('variant_sku');
            $table->json('note')->nullable(); // e.g. {"tr": "çift hazneli varyant", "en": "double bowl variant"}
            $table->timestamps();

            $table->index('variant_sku'); // Dealers search variant codes directly.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
