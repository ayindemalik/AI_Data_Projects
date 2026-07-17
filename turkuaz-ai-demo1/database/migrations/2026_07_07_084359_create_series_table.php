<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 💡 This check skips the code block if the table already exists
        if (!Schema::hasTable('series')) {
            Schema::create('series', function (Blueprint $table) {
                $table->id();
                $table->json('name');
                $table->json('description')->nullable();
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
