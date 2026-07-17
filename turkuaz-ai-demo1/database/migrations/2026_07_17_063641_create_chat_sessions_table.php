<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();                    // Identifies the session for guests (no login required)
            $table->unsignedBigInteger('user_id')->nullable();  // Null for guests
            $table->string('locale', 5)->default('tr');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
