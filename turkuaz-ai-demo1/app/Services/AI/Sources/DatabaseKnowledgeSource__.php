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
        // Code-like tokens (e.g. "9SC1663S02", "HC00106PB00") get an exact lookup first.
        $products = collect();

        if (preg_match('/\b[0-9A-Z]{6,}\b/i', $query, $m)) {
            $byCode = $this->search->findByCode($m[0]);
            if ($byCode) {
                $products->push($byCode);
            }
        }

        if ($products->isEmpty()) {
            $products = $this->search->search($query, 5);
        }

        if ($products->isEmpty()) {
            return ['context' => '', 'product_ids' => []];
        }

        $context = $products
            ->map(fn (Product $p) => $this->serializeProduct($p, $includeProCodes, $locale))
            ->implode("\n---\n");

        return [
            'context' => $context,
            'product_ids' => $products->pluck('id')->all(),
        ];
    }

    /**
     * Compact plain-text spec sheet for one product. Generic by design:
     * it walks whatever relations exist (colors, measures, documents), so
     * new measures/colors/document types appear here automatically with
     * no changes — the payoff of the extensible attribute design.
     *
     * SECURITY NOTE: pro codes (sku, variant codes) are only ever included
     * when $includeProCodes is true. Consumer-facing requests never have
     * codes in the context, so the model cannot leak them.
     */
    private function serializeProduct(Product $p, bool $includeProCodes, string $locale): string
    {
        $t = fn (?array $field) => $field[$locale] ?? $field['tr'] ?? $field['en'] ?? null;

        $lines = [];
        $lines[] = 'Ürün: ' . $t($p->name);

        if ($p->category) {
            $lines[] = 'Kategori: ' . $t($p->category->name)
                . ($p->subcategory ? ' > ' . $t($p->subcategory->name) : '');
        }

        if ($p->series) {
            $lines[] = 'Seri: ' . $t($p->series->name);
        }

        if ($p->dimensions) {
            $lines[] = 'Ölçüler: ' . $p->dimensions;
        }

        foreach ($p->measures as $measure) {
            $lines[] = $t($measure->name) . ': ' . rtrim(rtrim((string) $measure->pivot->value, '0'), '.') . ' ' . $measure->unit;
        }

        if ($p->colors->isNotEmpty()) {
            $lines[] = 'Renkler: ' . $p->colors->map(fn ($c) => $t($c->name))->implode(', ');
        }

        if ($desc = $t($p->description)) {
            $lines[] = 'Açıklama: ' . mb_substr($desc, 0, 600);
        }

        $docs = $p->documents->where('status', 'active');
        if ($docs->isNotEmpty()) {
            $docList = $docs->map(function ($d) use ($t, $locale) {
                return $t($d->title) . ' (' . $d->fileUrl($locale) . ')';
            })->implode('; ');
            $lines[] = 'Dokümanlar: ' . $docList;
        }

        if ($includeProCodes) {
            if ($p->sku) {
                $lines[] = 'Ürün Kodu (SKU): ' . $p->sku;
            }
            if ($p->variants->isNotEmpty()) {
                $lines[] = 'Varyant Kodları: ' . $p->variants->map(function ($v) use ($t) {
                    $note = $t($v->note);
                    return $v->variant_sku . ($note ? " ({$note})" : '');
                })->implode(', ');
            }
        }

        return implode("\n", $lines);
    }
}
