<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductSearchService
{
    /**
     * Find the products most relevant to a free-text query, using the same
     * scoring idea as the original demo's scoreProduct(), but against the
     * real database. Returns products with relations eager-loaded, ready
     * for serialization into AI context.
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            return collect();
        }

        // Pull candidate products with everything the serializer needs.
        // For catalogs of a few hundred/thousand products this in-memory
        // scoring is fast and lets us score across relations (series,
        // category names in both languages) which SQL LIKE can't do cleanly.
        $products = Product::query()
            ->with(['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'])
            ->active()
            ->get();

        return $products
            ->map(function (Product $product) use ($terms) {
                return ['product' => $product, 'score' => $this->score($product, $terms)];
            })
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /**
     * Direct lookup by exact SKU or variant SKU (dealers paste codes).
     */
    public function findByCode(string $code): ?Product
    {
        $code = trim($code);

        $product = Product::with(['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'])
            ->where('sku', $code)
            ->first();

        if ($product) {
            return $product;
        }

        return Product::with(['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'])
            ->whereHas('variants', fn ($q) => $q->where('variant_sku', $code))
            ->first();
    }

    private function score(Product $product, array $terms): int
    {
        $haystacks = [
            // Higher weights for more specific fields.
            8 => $this->normalize((string) $product->sku),
            6 => $this->normalize(($product->name['tr'] ?? '') . ' ' . ($product->name['en'] ?? '')),
            4 => $this->normalize(
                ($product->series?->name['tr'] ?? '') . ' ' . ($product->series?->name['en'] ?? '')
            ),
            3 => $this->normalize(
                ($product->subcategory?->name['tr'] ?? '') . ' ' . ($product->subcategory?->name['en'] ?? '') . ' ' .
                ($product->category?->name['tr'] ?? '') . ' ' . ($product->category?->name['en'] ?? '')
            ),
            2 => $this->normalize(
                $product->colors->map(fn ($c) => ($c->name['tr'] ?? '') . ' ' . ($c->name['en'] ?? ''))->implode(' ') . ' ' .
                (string) $product->dimensions
            ),
            1 => $this->normalize(
                ($product->description['tr'] ?? '') . ' ' . ($product->description['en'] ?? '')
            ),
        ];

        $score = 0;

        foreach ($terms as $term) {
            foreach ($haystacks as $weight => $haystack) {
                if ($haystack !== '' && str_contains($haystack, $term)) {
                    $score += $weight;
                }
            }
        }

        return $score;
    }

    /**
     * Lowercase + strip Turkish diacritics, same as normalizeTR() in the demo,
     * so "gomme rezervuar" matches "Gömme Rezervuar".
     */
    private function normalize(string $text): string
    {
        $map = [
            'ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i',
            'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u',
        ];

        return mb_strtolower(strtr($text, $map));
    }

    private function tokenize(string $query): array
    {
        $normalized = $this->normalize($query);
        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // Drop very short filler words; keep codes and meaningful terms.
        return array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3));
    }
}
