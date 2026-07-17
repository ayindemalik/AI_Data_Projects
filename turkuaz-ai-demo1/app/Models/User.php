<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Fields that can be mass-assigned via User::create([...]).
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id', // Every user has exactly ONE role — no multi-role complexity.
    ];

    // Fields that should never be exposed when the model is converted to an array/JSON.
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Automatic type conversion for these fields.
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The single role this user belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * True if this user's role matches the given slug.
     * Example: $user->hasRole('administrator')
     */
    public function hasRole(string $slug): bool
    {
        return $this->role?->slug === $slug;
    }

    /**
     * True if this user's role matches ANY of the given slugs.
     * Example: $user->hasAnyRole(['administrator', 'sales'])
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->role !== null && in_array($this->role->slug, $slugs, true);
    }

    /**
     * True if this user's role carries the given permission slug.
     * Example: $user->hasPermission('view-product-codes')
     */
    public function hasPermission(string $slug): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()->where('slug', $slug)->exists();
    }
}
