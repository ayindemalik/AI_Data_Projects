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
        'category_id', 'subcategory_id', 'series_id',
        'sku', 'slug', 'name', 'description', 'dimensions', 'status',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

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

    /**
     * Technical documents attached to this product (datasheets, 2D/3D
     * drawings, installation guides, etc). Added in the Documents module;
     * this reverse relation was missing until the assistant needed it.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}