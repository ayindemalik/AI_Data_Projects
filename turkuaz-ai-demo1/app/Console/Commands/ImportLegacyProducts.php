<?php

namespace App\Console\Commands;

use App\Models\Measure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyProducts extends Command
{
    /**
     * Reads the legacy turkuaz6 database (loaded into a temporary MySQL
     * database by the accompanying shell script) and maps its products
     * into the new schema:
     *
     *   legacy products.title_tr/en        -> products.name (json)
     *   legacy products.description_tr/en  -> products.description (json, HTML stripped)
     *   legacy products.category           -> products.category_id     (IDs preserved earlier)
     *   legacy products.sub_category       -> products.subcategory_id  (IDs preserved earlier)
     *   legacy products.collection         -> products.series_id       (IDs preserved earlier)
     *   legacy products.product_code       -> products.sku (deduplicated)
     *   legacy products.dimensions         -> products.dimensions
     *   legacy products.en_status          -> products.status (1=active, 0=inactive) **assumption, see --help
     *   legacy products.color              -> product_colors pivot
     *   legacy products.image              -> product_images (sort_order 0, external URL)
     *   legacy product_images rows         -> product_images (gallery, external URLs)
     *   legacy products.sub_filter         -> "Height" measure value, ONLY when the id
     *                                          exists in legacy product_height (ids 29-48)
     *
     * Legacy product IDs are preserved, consistent with the catalog import.
     * Safe to re-run: keyed on id / updateOrInsert throughout.
     */
    protected $signature = 'import:legacy-products
                            {--legacy-db=turkuaz_legacy : Name of the temporary MySQL database holding the legacy dump}';

    protected $description = 'Import products from the legacy turkuaz6 dump (loaded in a temp MySQL DB) into the new schema.';

    public function handle(): int
    {
        // Point a throwaway connection at the temp legacy database, reusing
        // the app's own MySQL credentials.
        config(['database.connections.legacy_import' => array_merge(
            config('database.connections.mysql'),
            ['database' => $this->option('legacy-db')]
        )]);

        $legacy = DB::connection('legacy_import');

        // ---- Preload lookups ----

        // product_height: legacy filter id -> height in cm (e.g. 29 -> 28, 48 -> 37.5)
        $heights = $legacy->table('product_height')->pluck('title_tr', 'id')
            ->map(fn ($v) => (float) str_replace(',', '.', $v));

        // Ensure a "Height" measure exists to receive those values.
        $heightMeasure = Measure::withTrashed()->firstOrCreate(
            ['slug' => 'height'],
            ['name' => ['tr' => 'Yükseklik', 'en' => 'Height'], 'unit' => 'cm', 'status' => 'active']
        );

        // Valid FK targets, so we can warn on orphans instead of silently importing them.
        $validCategories = DB::table('categories')->pluck('id')->flip();
        $validSubcategories = DB::table('subcategories')->pluck('id')->flip();
        $validSeries = DB::table('series')->pluck('id')->flip();
        $validColors = DB::table('colors')->pluck('id')->flip();

        $rows = $legacy->table('products')->orderBy('id')->get();
        $this->info("Found {$rows->count()} legacy products.");

        $seenSkus = [];
        $skippedSkuDupes = [];
        $orphanWarnings = [];
        $imported = 0;

        foreach ($rows as $row) {
            // --- SKU dedupe: unique column; keep first occurrence, null out repeats ---
            $sku = trim((string) $row->product_code) ?: null;
            if ($sku !== null) {
                if (isset($seenSkus[$sku])) {
                    $skippedSkuDupes[] = "Product {$row->id}: duplicate SKU '{$sku}' (already on product {$seenSkus[$sku]}) — imported with NULL sku";
                    $sku = null;
                } else {
                    $seenSkus[$sku] = $row->id;
                }
            }

            // --- Orphan checks (plain columns, no DB constraint — so we validate here) ---
            $categoryId = $this->validateId($row->category, $validCategories, $row->id, 'category', $orphanWarnings);
            $subcategoryId = $this->validateId($row->sub_category, $validSubcategories, $row->id, 'subcategory', $orphanWarnings);
            $seriesId = $this->validateId($row->collection, $validSeries, $row->id, 'series', $orphanWarnings);

            $titleTr = trim($row->title_tr);
            $titleEn = trim((string) $row->title_en) ?: null;

            DB::table('products')->updateOrInsert(
                ['id' => $row->id],
                [
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategoryId,
                    'series_id' => $seriesId,
                    'sku' => $sku,
                    'slug' => Str::slug(($titleEn ?: $titleTr)) . '-' . $row->id, // id suffix guarantees uniqueness
                    'name' => json_encode(['tr' => $titleTr, 'en' => $titleEn ?: $titleTr], JSON_UNESCAPED_UNICODE),
                    'description' => json_encode([
                        'tr' => $this->cleanHtml($row->description_tr),
                        'en' => $this->cleanHtml($row->description_en),
                    ], JSON_UNESCAPED_UNICODE),
                    'dimensions' => $row->dimensions ?: null,
                    // ASSUMPTION: legacy en_status (1/0) is treated as the product's
                    // enabled/disabled flag. If it turns out to mean "visible on the
                    // English site", flip these back to 'active' and re-run.
                    'status' => ((int) $row->en_status) === 1 ? 'active' : 'inactive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // --- Color pivot ---
            if ($row->color && isset($validColors[$row->color])) {
                DB::table('product_colors')->updateOrInsert(
                    ['product_id' => $row->id, 'color_id' => $row->color]
                );
            }

            // --- Height measure from sub_filter, only when it maps to product_height ---
            if ($row->sub_filter !== null && $heights->has((int) $row->sub_filter)) {
                DB::table('product_measures')->updateOrInsert(
                    ['product_id' => $row->id, 'measure_id' => $heightMeasure->id],
                    ['value' => $heights[(int) $row->sub_filter]]
                );
            }

            // --- Main image (external URL, sort 0) ---
            $mainImage = trim((string) $row->image);
            if ($mainImage !== '') {
                $exists = DB::table('product_images')
                    ->where('product_id', $row->id)->where('path', $mainImage)->exists();
                if (!$exists) {
                    DB::table('product_images')->insert([
                        'product_id' => $row->id,
                        'path' => $mainImage,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $imported++;
        }

        $this->syncAutoIncrement('products');

        // --- Gallery images from legacy product_images ---
        $galleryCount = 0;
        foreach ($legacy->table('product_images')->orderBy('id')->get() as $img) {
            if (!DB::table('products')->where('id', $img->product_id)->exists()) {
                $orphanWarnings[] = "Gallery image {$img->id}: product {$img->product_id} not found — skipped";
                continue;
            }

            $exists = DB::table('product_images')
                ->where('product_id', $img->product_id)->where('path', $img->image)->exists();

            if (!$exists) {
                $nextSort = (int) DB::table('product_images')
                    ->where('product_id', $img->product_id)->max('sort_order') + 1;

                DB::table('product_images')->insert([
                    'product_id' => $img->product_id,
                    'path' => $img->image,
                    'sort_order' => $nextSort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $galleryCount++;
            }
        }

        // --- Report ---
        $this->info("Imported/updated {$imported} products, {$galleryCount} gallery images.");

        foreach ($skippedSkuDupes as $w) {
            $this->warn($w);
        }
        foreach ($orphanWarnings as $w) {
            $this->warn($w);
        }

        $this->info('Done. Legacy IDs preserved throughout.');

        return self::SUCCESS;
    }

    /**
     * Returns the id if it exists in the lookup, otherwise null + a warning.
     */
    private function validateId($id, $validSet, int $productId, string $label, array &$warnings): ?int
    {
        if ($id === null) {
            return null;
        }

        if (!isset($validSet[(int) $id])) {
            $warnings[] = "Product {$productId}: {$label} id {$id} not found — stored as NULL";
            return null;
        }

        return (int) $id;
    }

    /**
     * Legacy descriptions are messy WYSIWYG HTML (MSO markup, inline styles,
     * embedded tables). For clean text the AI assistant can consume, convert
     * block/bullet boundaries to newlines, strip all tags, squeeze whitespace.
     */
    private function cleanHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = preg_replace('/<(li|p|br|tr|div)[^>]*>/i', "\n", $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);          // collapse spaces
        $text = preg_replace('/\n\s*\n+/', "\n", $text);        // collapse blank lines
        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    private function syncAutoIncrement(string $table): void
    {
        $maxId = DB::table($table)->max('id');

        if ($maxId) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 1));
        }
    }
}
