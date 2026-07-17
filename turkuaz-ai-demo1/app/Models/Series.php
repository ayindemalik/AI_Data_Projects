<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Series extends Model
{
    use SoftDeletes, HasTranslations;

    protected $fillable = ['name', 'description', 'slug', 'status'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
