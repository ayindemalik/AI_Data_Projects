<?php

namespace App\Concerns;

trait HasTranslations
{
    /**
     * Get a translatable field's value for the current locale, falling back
     * to Turkish, then to whatever value exists.
     * Usage: $category->translate('name')
     */
    public function translate(string $field): ?string
    {
        $values = $this->{$field} ?? [];

        if (!is_array($values)) {
            return null;
        }

        return $values[app()->getLocale()] ?? $values['tr'] ?? (reset($values) ?: null);
    }

    /**
     * Search a translatable JSON field across both tr/en values.
     * Usage: Category::searchName($term)->get()
     */
    public function scopeSearchName($query, ?string $term)
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->whereRaw("JSON_EXTRACT(name, '$.tr') LIKE ?", ["%{$term}%"])
              ->orWhereRaw("JSON_EXTRACT(name, '$.en') LIKE ?", ["%{$term}%"]);
        });
    }

    /**
     * Only records marked active.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
