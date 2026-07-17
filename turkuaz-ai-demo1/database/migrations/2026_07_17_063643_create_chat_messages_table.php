<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);                       // user | assistant
            $table->text('content');
            $table->json('matched_product_ids')->nullable();  // Traceability: which products informed this answer
            $table->string('source', 30)->nullable();         // db | openai | rule (price/showroom shortcuts)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
