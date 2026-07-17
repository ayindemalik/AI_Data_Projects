<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends Model
{
    use HasTranslations;

    protected $fillable = ['product_id', 'path', 'alt_text', 'sort_order'];

    protected $casts = [
        'alt_text' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Public URL for this image.
     * - Admin-uploaded images store a relative path on the 'public' disk.
     * - Legacy-imported images store the original absolute CDN URL as-is;
     *   those are returned unchanged.
     */
    public function getUrlAttribute(): string
    {
        if (Str::startsWith($this->path, ['http://', 'https://'])) {
            return $this->path;
        }

        return Storage::disk('public')->url($this->path);
    }
}
