<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Document extends Model
{
    use SoftDeletes, HasTranslations;

    public const TYPES = ['general', 'datasheet', '2d', '3d', 'setup', 'warranty', 'service', 'spare_part'];

    protected $fillable = ['document_category_id', 'product_id', 'type', 'title', 'file', 'status'];

    protected $casts = [
        'title' => 'array',
        'file' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Resolve the file URL for a locale, falling back TR <-> EN.
     * Handles both absolute legacy CDN URLs and relative admin-upload paths.
     */
    public function fileUrl(?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();
        $value = $this->file[$locale] ?? $this->file['tr'] ?? $this->file['en'] ?? null;

        if ($value === null) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
