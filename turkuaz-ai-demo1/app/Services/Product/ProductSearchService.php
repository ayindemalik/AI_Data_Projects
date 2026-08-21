<?php

namespace App\Services\Product;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Series;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Builder;
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
     * "60 cm mat siyah lavabo"               -> subcategory=Lavabo AND colour AND 60 cm
     * "91x51 tezgah üstü bir şey"            -> no structure -> keyword scoring
     *
     * Colour and size narrow the listing but never kill it: asking for a 60 cm
     * mat black basin when none exists should answer with mat black basins,
     * not with fuzzy keyword guesses, so the filters are dropped one at a time
     * (size first, then colour) until something comes back.
     *
     * Returns ['products' => Collection, 'filtered' => bool, 'applied' => array]
     * — 'filtered' tells the caller whether this is a precise catalog listing
     * (safe to show as a list) or a fuzzy guess, and 'applied' names the
     * filters that actually survived, for diagnostics.
     */
    public function searchWithIntent(string $query, int $limit = 10): array
    {
        $normalizedQuery = $this->normalize($query);

        $seriesId = $this->detectByName(Series::active()->get(), $normalizedQuery);
        $subcategoryId = $this->detectByName(Subcategory::active()->get(), $normalizedQuery, $this->subcategorySynonyms());
        $categoryId = $this->detectByName(Category::active()->get(), $normalizedQuery);
        $colorIds = $this->detectColorIds($normalizedQuery);
        $measure = $this->detectMeasure($normalizedQuery);

        $taxonomy = $seriesId || $subcategoryId || $categoryId;

        if ($taxonomy || $colorIds || $measure) {
            foreach ([[$colorIds, $measure], [$colorIds, null], [null, null]] as [$colors, $size]) {
                // Never widen all the way to "no filter at all" — that is the
                // whole catalogue in name order, not an answer to anything.
                if (!$taxonomy && !$colors && !$size) {
                    continue;
                }

                $products = $this->structured($seriesId, $subcategoryId, $categoryId, $colors, $size, $limit);

                if ($products->isNotEmpty()) {
                    return [
                        'products' => $products,
                        'filtered' => true,
                        'applied' => array_filter([
                            'series_id' => $seriesId,
                            'subcategory_id' => $subcategoryId,
                            'category_id' => $categoryId && !$subcategoryId ? $categoryId : null,
                            'color_ids' => $colors,
                            'measure' => $size,
                        ]),
                    ];
                }
            }
        }

        return ['products' => $this->search($query, 5), 'filtered' => false, 'applied' => []];
    }

    /**
     * The precise listing query. Split out so searchWithIntent can run it
     * more than once while it relaxes the optional filters.
     */
    private function structured(
        ?int $seriesId,
        ?int $subcategoryId,
        ?int $categoryId,
        ?array $colorIds,
        ?array $measure,
        int $limit
    ): Collection {
        return Product::query()
            ->with(['category', 'subcategory', 'series', 'color', 'measures', 'variants', 'documents'])
            ->active()
            ->when($seriesId, fn ($q) => $q->where('series_id', $seriesId))
            ->when($subcategoryId, fn ($q) => $q->where('subcategory_id', $subcategoryId))
            // Only apply category when subcategory didn't already narrow it,
            // to avoid over-restricting (subcategory implies its category).
            ->when($categoryId && !$subcategoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($colorIds, fn ($q) => $q->whereIn('color_id', $colorIds))
            ->when($measure, fn ($q) => $this->applyMeasure($q, $measure))
            ->limit($limit)
            ->get();
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
            ->with(['category', 'subcategory', 'series', 'color', 'measures', 'variants', 'documents'])
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
        $with = ['category', 'subcategory', 'series', 'color', 'measures', 'variants', 'documents'];

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
     * Colours named in the query, as products.color_id values.
     *
     * Two passes, because colour names overlap. A full name wins outright
     * ("mat siyah" -> Mat Siyah). Failing that, a single distinguishing word is
     * spread over every colour carrying it, so "siyah lavabo" offers both Mat
     * Siyah and Parlak Siyah rather than nothing — the shopper said black, not
     * which finish.
     *
     * Colours nobody stocks are excluded first: the catalog carries a bare
     * "Siyah" with zero products, and matching it would turn a good question
     * into an empty listing.
     *
     * @return array<int>|null
     */
    private function detectColorIds(string $normalizedQuery): ?array
    {
        $stocked = Product::query()->whereNotNull('color_id')->distinct()->pluck('color_id');

        if ($stocked->isEmpty()) {
            return null;
        }

        $colors = Color::active()->whereIn('id', $stocked)->get();

        if ($exact = $this->detectByName($colors, $normalizedQuery)) {
            return [$exact];
        }

        $queryWords = $this->words($normalizedQuery);
        $ids = [];

        foreach ($colors as $color) {
            foreach (['tr', 'en'] as $lang) {
                foreach ($this->words($this->normalize($color->name[$lang] ?? '')) as $nameWord) {
                    if (mb_strlen($nameWord) < 3) {
                        continue;
                    }

                    foreach ($queryWords as $queryWord) {
                        if ($this->wordMatches($queryWord, $nameWord, false)) {
                            $ids[] = $color->id;
                            continue 4;
                        }
                    }
                }
            }
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }

    /**
     * A size named in the query.
     *
     * "55x42" is unambiguous on its own. A lone number is not — "60" could be a
     * quantity or part of a code — so the unit is required for that form, which
     * is also how customers actually write it ("60 cm lavabo").
     *
     * @return array{type:string, value:string}|null
     */
    private function detectMeasure(string $normalizedQuery): ?array
    {
        if (preg_match('/(\d{1,4})\s*x\s*(\d{1,4})/', $normalizedQuery, $m)) {
            return ['type' => 'pair', 'value' => $m[1].'x'.$m[2]];
        }

        if (preg_match('/(?<![\dx])(\d{2,4})\s*cm\b/', $normalizedQuery, $m)) {
            return ['type' => 'single', 'value' => $m[1]];
        }

        return null;
    }

    /**
     * Match a size against products.dimensions ("60x45 cm", "48 cm"), falling
     * back to the Turkish name because 163 catalog rows carry no dimensions at
     * all and still name their size in the title.
     *
     * LIKE rather than a regex: the test suite runs on SQLite, which has no
     * REGEXP operator.
     */
    private function applyMeasure(Builder $query, array $measure): Builder
    {
        $value = $measure['value'];

        return $query->where(function (Builder $q) use ($measure, $value) {
            if ($measure['type'] === 'pair') {
                $q->where('dimensions', 'like', $value.'%')
                  ->orWhere('name->tr', 'like', '%'.$value.'%');

                return;
            }

            // A single measure is the first figure of a WxH product, the second
            // of a HxW one, or the only figure the row carries.
            $q->where('dimensions', 'like', $value.'x%')
              ->orWhere('dimensions', 'like', '%x'.$value)
              ->orWhere('dimensions', 'like', '%x'.$value.' %')
              ->orWhere('dimensions', 'like', $value.' cm%')
              ->orWhere('name->tr', 'like', '%'.$value.' cm%');
        });
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
     * "arda".
     *
     * The leftover has to be an ACTUAL Turkish ending, not merely short.
     * Allowing any short remainder made "önerir" — the most ordinary way to
     * ask for a recommendation — match the series "One" and silently restrict
     * every recommendation question to that one series. "onerir" minus "one"
     * leaves "rir", which is not a suffix in Turkish; "ibizada" minus "ibiza"
     * leaves "da", which is.
     */
    private function wordMatches(string $queryWord, string $nameWord, bool $exactOnly): bool
    {
        if ($queryWord === $nameWord) {
            return true;
        }

        if ($exactOnly || !str_starts_with($queryWord, $nameWord)) {
            return false;
        }

        return in_array(mb_substr($queryWord, mb_strlen($nameWord)), $this->turkishSuffixes(), true);
    }

    /**
     * Noun endings a catalog term can legitimately pick up, already normalized
     * (ı->i, ü->u, ş->s ...) so they compare against normalize() output.
     *
     * Plural, case, possessive, and the common compounds of those. Extend
     * freely — this is the ONLY place these live.
     */
    private function turkishSuffixes(): array
    {
        return [
            // plural
            'lar', 'ler',
            // case
            'a', 'e', 'i', 'u', 'ya', 'ye', 'yi', 'yu',
            'da', 'de', 'ta', 'te', 'dan', 'den', 'tan', 'ten',
            'nda', 'nde', 'ndan', 'nden', 'in', 'un', 'nin', 'nun',
            // possessive
            'si', 'su', 'sa', 'se', 'miz', 'niz',
            // plural + case
            'larda', 'lerde', 'lardan', 'lerden', 'lari', 'leri',
            'larin', 'lerin', 'lara', 'lere', 'lari', 'leri',
            // derivational endings that still name the same thing
            'li', 'lu', 'lik', 'luk', 'siz', 'suz',
            'daki', 'deki', 'taki', 'teki',
            // "ile" contracted
            'la', 'le', 'yla', 'yle',
        ];
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
                ($product->color?->name['tr'] ?? '') . ' ' . ($product->color?->name['en'] ?? '') . ' ' .
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
