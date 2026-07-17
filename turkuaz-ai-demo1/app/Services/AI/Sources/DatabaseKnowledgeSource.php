<?php

namespace App\Services\AI\Sources;

use App\Models\Product;
use App\Services\AI\Contracts\KnowledgeSourceInterface;
use App\Services\Product\ProductSearchService;

class DatabaseKnowledgeSource implements KnowledgeSourceInterface
{
    public function __construct(private ProductSearchService $search)
    {
    }

    public function retrieve(string $query, bool $includeProCodes, string $locale): array
    {
        // Code-like tokens (e.g. "9SC1663S02") get an exact lookup first.
        if (preg_match('/\b[0-9A-Z-]{6,}\b/i', $query, $m)) {
            $byCode = $this->search->findByCode($m[0]);
            if ($byCode) {
                return [
                    'context' => $this->serializeFull($byCode, $includeProCodes, $locale),
                    'product_ids' => [$byCode->id],
                ];
            }
        }

        $result = $this->search->searchWithIntent($query, 10);
        $products = $result['products'];

        if ($products->isEmpty()) {
            return ['context' => '', 'product_ids' => []];
        }

        // 1-2 matches: full spec sheets. More: compact one-line-per-product
        // listing — enough for the model to present a clean list without
        // drowning it (and the token budget) in per-product detail.
        $context = $products->count() <= 2
            ? $products->map(fn (Product $p) => $this->serializeFull($p, $includeProCodes, $locale))->implode("\n---\n")
            : $products->map(fn (Product $p) => $this->serializeCompact($p, $includeProCodes, $locale))->implode("\n");

        return [
            'context' => $context,
            'product_ids' => $products->pluck('id')->all(),
        ];
    }

    /**
     * One line per product, for list answers.
     * e.g. "- İbiza Lavabo 61x51 | Lavabo | 61x51 cm | Renkler: Beyaz | SKU: 050100-u"
     */
    private function serializeCompact(Product $p, bool $includeProCodes, string $locale): string
    {
        $t = fn (?array $field) => $field[$locale] ?? $field['tr'] ?? $field['en'] ?? null;
        $labels = $this->labels($locale);

        $parts = [$t($p->name)];

        if ($p->subcategory) {
            $parts[] = $t($p->subcategory->name);
        }

        if ($p->dimensions) {
            $parts[] = $p->dimensions;
        }

        if ($p->colors->isNotEmpty()) {
            $parts[] = $labels['colors'] . ': ' . $p->colors->map(fn ($c) => $t($c->name))->implode(', ');
        }

        if ($includeProCodes && $p->sku) {
            $parts[] = 'SKU: ' . $p->sku;
        }

        return '- ' . implode(' | ', array_filter($parts));
    }

    /**
     * Full spec sheet, used for 1-2 matches or exact code lookups.
     * Labels are localized so the context language matches the reply language.
     */
    private function serializeFull(Product $p, bool $includeProCodes, string $locale): string
    {
        $t = fn (?array $field) => $field[$locale] ?? $field['tr'] ?? $field['en'] ?? null;
        $labels = $this->labels($locale);

        $lines = [];
        $lines[] = $labels['product'] . ': ' . $t($p->name);

        if ($p->category) {
            $lines[] = $labels['category'] . ': ' . $t($p->category->name)
                . ($p->subcategory ? ' > ' . $t($p->subcategory->name) : '');
        }

        if ($p->series) {
            $lines[] = $labels['series'] . ': ' . $t($p->series->name);
        }

        if ($p->dimensions) {
            $lines[] = $labels['dimensions'] . ': ' . $p->dimensions;
        }

        foreach ($p->measures as $measure) {
            $lines[] = $t($measure->name) . ': ' . rtrim(rtrim((string) $measure->pivot->value, '0'), '.') . ' ' . $measure->unit;
        }

        if ($p->colors->isNotEmpty()) {
            $lines[] = $labels['colors'] . ': ' . $p->colors->map(fn ($c) => $t($c->name))->implode(', ');
        }

        if ($desc = $t($p->description)) {
            $lines[] = $labels['description'] . ': ' . mb_substr($desc, 0, 400);
        }

        $docs = $p->documents->where('status', 'active');
        if ($docs->isNotEmpty()) {
            $docList = $docs->map(fn ($d) => $t($d->title) . ' (' . $d->fileUrl($locale) . ')')->implode('; ');
            $lines[] = $labels['documents'] . ': ' . $docList;
        }

        if ($includeProCodes) {
            if ($p->sku) {
                $lines[] = $labels['sku'] . ': ' . $p->sku;
            }
            if ($p->variants->isNotEmpty()) {
                $lines[] = $labels['variants'] . ': ' . $p->variants->map(function ($v) use ($t) {
                    $note = $t($v->note);
                    return $v->variant_sku . ($note ? " ({$note})" : '');
                })->implode(', ');
            }
        }

        return implode("\n", $lines);
    }

    private function labels(string $locale): array
    {
        return $locale === 'en'
            ? [
                'product' => 'Product', 'category' => 'Category', 'series' => 'Series',
                'dimensions' => 'Dimensions', 'colors' => 'Colors', 'description' => 'Description',
                'documents' => 'Documents', 'sku' => 'Product Code (SKU)', 'variants' => 'Variant Codes',
            ]
            : [
                'product' => 'Ürün', 'category' => 'Kategori', 'series' => 'Seri',
                'dimensions' => 'Ölçüler', 'colors' => 'Renkler', 'description' => 'Açıklama',
                'documents' => 'Dokümanlar', 'sku' => 'Ürün Kodu (SKU)', 'variants' => 'Varyant Kodları',
            ];
    }
}
