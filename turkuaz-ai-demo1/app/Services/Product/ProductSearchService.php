<?php

namespace App\Services\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\Series;
use App\Models\Subcategory;
use Illuminate\Support\Collection;

class ProductSearchService
{
    /**
     * Structured search: first detect explicit intent (a series, category, or
     * subcategory named in the query) and filter precisely on it; only fall
     * back to fuzzy keyword scoring when no structure is detected.
     *
     * "İbiza serisinde hangi lavabolar var?" -> series=İbiza AND subcategory=Lavabo
     * "gömme rezervuar"                      -> category=Gömme Rezervuar
     * "91x51 tezgah üstü bir şey"            -> no structure -> keyword scoring
     *
     * Returns ['products' => Collection, 'filtered' => bool] — 'filtered'
     * tells the caller whether this is a precise catalog listing (safe to
     * show as a list) or a fuzzy guess (best-effort matches).
     */
    public function searchWithIntent(string $query, int $limit = 10): array
    {
        $normalizedQuery = $this->normalize($query);

        $seriesId = $this->detectByName(Series::active()->get(), $normalizedQuery);
        $subcategoryId = $this->detectByName(Subcategory::active()->get(), $normalizedQuery, $this->subcategorySynonyms());
        $categoryId = $this->detectByName(Category::active()->get(), $normalizedQuery);

        if ($seriesId || $subcategoryId || $categoryId) {
            $products = Product::query()
                ->with(['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'])
                ->active()
                ->when($seriesId, fn ($q) => $q->where('series_id', $seriesId))
                ->when($subcategoryId, fn ($q) => $q->where('subcategory_id', $subcategoryId))
                // Only apply category when subcategory didn't already narrow it,
                // to avoid over-restricting (subcategory implies its category).
                ->when($categoryId && !$subcategoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->limit($limit)
                ->get();

            if ($products->isNotEmpty()) {
                return ['products' => $products, 'filtered' => true];
            }
        }

        return ['products' => $this->search($query, 5), 'filtered' => false];
    }

    /**
     * Fuzzy keyword scoring across name/sku/series/category/colors/description.
     */
    public function search(string $query, int $limit = 5): Collection
    {
        $terms = $this->tokenize($query);

        if (empty($terms)) {
            return collect();
        }

        $products = Product::query()
            ->with(['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'])
            ->active()
            ->get();

        return $products
            ->map(fn (Product $product) => ['product' => $product, 'score' => $this->score($product, $terms)])
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
        $with = ['category', 'subcategory', 'series', 'colors', 'measures', 'variants', 'documents'];

        $product = Product::with($with)
            ->where('sku', $code)
            ->orWhere('sku_new', $code)
            ->first();

        if ($product) {
            return $product;
        }

        return Product::with($with)
            ->whereHas('variants', fn ($q) => $q->where('variant_sku', $code))
            ->first();
    }

    /**
     * Find the model whose (normalized) TR or EN name appears in the query as
     * WHOLE WORDS. Synonyms extend matching for common phrasings
     * ("tuvalet" -> Klozet).
     *
     * Whole words matter: a plain substring test matches the series "Arda"
     * inside "lavabolarda" and "Arya" inside "bataryalar", which silently
     * answers a question about one series with products from another.
     *
     * Multi-word names ("gömme rezervuar") must appear as a consecutive run of
     * words. Names with more words win over shorter ones, and exact word
     * matches win over suffixed ones, so the most specific reading is taken.
     */
    private function detectByName(Collection $models, string $normalizedQuery, array $synonyms = []): ?int
    {
        $queryWords = $this->words($normalizedQuery);

        if ($queryWords === []) {
            return null;
        }

        $candidates = [];

        foreach ($models as $model) {
            foreach (['tr', 'en'] as $lang) {
                $name = $this->normalize($model->name[$lang] ?? '');
                if ($name !== '' && mb_strlen($name) >= 3) {
                    $candidates[] = ['words' => $this->words($name), 'id' => $model->id];
                }
            }
        }

        foreach ($synonyms as $needle => $canonicalName) {
            $match = $models->first(function ($m) use ($canonicalName) {
                return $this->normalize($m->name['tr'] ?? '') === $this->normalize($canonicalName);
            });
            if ($match) {
                $candidates[] = ['words' => $this->words($this->normalize($needle)), 'id' => $match->id];
            }
        }

        $candidates = array_values(array_filter($candidates, fn ($c) => $c['words'] !== []));

        usort($candidates, function ($a, $b) {
            return [count($b['words']), mb_strlen(implode($b['words']))]
                <=> [count($a['words']), mb_strlen(implode($a['words']))];
        });

        // Exact word matches first, then allow Turkish suffixes ("Aqua" in
        // "aquada"). Two passes rather than one so a suffixed match can never
        // beat an exact one that appears later in the list.
        foreach ([true, false] as $exactOnly) {
            foreach ($candidates as $candidate) {
                if ($this->containsWordRun($queryWords, $candidate['words'], $exactOnly)) {
                    return $candidate['id'];
                }
            }
        }

        return null;
    }

    /**
     * True when $needle appears in $haystack as a run of consecutive words.
     */
    private function containsWordRun(array $haystack, array $needle, bool $exactOnly): bool
    {
        $limit = count($haystack) - count($needle);

        for ($offset = 0; $offset <= $limit; $offset++) {
            $matched = true;

            foreach ($needle as $i => $word) {
                if (!$this->wordMatches($haystack[$offset + $i], $word, $exactOnly)) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turkish attaches case/plural endings to the end of a word, so a real
     * match is always a PREFIX: "lavabolarda" starts with "lavabo", and
     * "aquada" starts with "aqua" — while "lavabolarda" does not start with
     * "arda". The suffix is capped so a short name can't swallow a longer
     * unrelated word.
     */
    private function wordMatches(string $queryWord, string $nameWord, bool $exactOnly): bool
    {
        if ($queryWord === $nameWord) {
            return true;
        }

        if ($exactOnly) {
            return false;
        }

        return str_starts_with($queryWord, $nameWord)
            && mb_strlen($queryWord) - mb_strlen($nameWord) <= 5;
    }

    /**
     * Split already-normalized text into comparable words.
     */
    private function words(string $normalized): array
    {
        return preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Common user phrasings mapped to canonical subcategory TR names.
     * Extend freely — this is the ONLY place these synonyms live.
     */
    private function subcategorySynonyms(): array
    {
        return [
            'lavabolar' => 'Lavabo',
            'washbasin' => 'Lavabo',
            'basin' => 'Lavabo',
            'tuvalet' => 'Klozet',
            'toilet' => 'Klozet',
            'klozetler' => 'Klozet',
            'wc' => 'Klozet',
        ];
    }

    private function score(Product $product, array $terms): int
    {
        $haystacks = [
            8 => $this->normalize((string) $product->sku . ' ' . (string) $product->sku_new),
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

        return array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3));
    }
}
