<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, HasTranslations;

    protected $fillable = [
        'category_id', 'subcategory_id', 'product_type_id', 'series_id', 'color_id',
        'sku', 'sku_new', 'kg', 'palet_adeti', 'catalog_synced_at',
        'slug', 'name', 'description', 'dimensions', 'status',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'kg' => 'decimal:2',
        'catalog_synced_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    /**
     * Third catalog level: Category > Subcategory > Product Type.
     * product_type_id is a plain column (no DB-level FK), matching the
     * legacy-import pattern used by the other lookups.
     */
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Default / primary colour. color_id is a plain column (no DB-level FK,
     * matching the legacy-import pattern) but this relation lets code and
     * views resolve the colour conveniently.
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /**
     * All colours a product is offered in (many-to-many). The default colour
     * (color_id) is also mirrored here so existing colour-based UI keeps working.
     */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'product_colors');
    }

    public function measures(): BelongsToMany
    {
        return $this->belongsToMany(Measure::class, 'product_measures')->withPivot('value');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
