<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();

                // Plain columns, no DB-level FK constraint — consistent with the
                // legacy-import pattern used across categories/subcategories/products.
                $table->unsignedBigInteger('document_category_id')->nullable(); // corporate docs (certificates, policies)
                $table->unsignedBigInteger('product_id')->nullable();           // product technical docs (datasheet, 2D, 3D...)

                // Document type. 'general' for corporate/knowledge-base docs; the rest
                // mirror the legacy per-product doc columns exactly.
                $table->string('type')->default('general'); // general | datasheet | 2d | 3d | setup | warranty | service | spare_part

                $table->json('title');           // {"tr": "...", "en": "..."}
                // Legacy docs have separate TR and EN files, so file is bilingual too.
                // Values can be absolute CDN URLs (legacy) or relative paths on the
                // 'public' disk (future admin uploads) — same dual pattern as ProductImage.
                $table->json('file');            // {"tr": "url-or-path", "en": "url-or-path-or-null"}

                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();

                $table->index('document_category_id');
                $table->index('product_id');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
