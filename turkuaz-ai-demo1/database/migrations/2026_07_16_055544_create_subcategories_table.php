<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            // Plain column, no DB-level FK constraint — matches the legacy schema
            // and lets us preserve legacy IDs directly without a lookup/remap step.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcategories');
    }
};
