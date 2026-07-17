<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    use SoftDeletes, HasTranslations;

    protected $fillable = ['name', 'slug', 'status'];

    protected $casts = [
        'name' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
