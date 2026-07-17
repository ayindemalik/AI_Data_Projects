<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use SoftDeletes, HasTranslations;

    protected $fillable = ['name', 'description', 'slug', 'status'];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
    ];

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }
}
