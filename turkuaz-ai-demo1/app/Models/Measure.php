<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Measure extends Model
{
    use SoftDeletes, HasTranslations;

    protected $fillable = ['name', 'unit', 'slug', 'status'];

    protected $casts = [
        'name' => 'array',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_measures')->withPivot('value');
    }
}
