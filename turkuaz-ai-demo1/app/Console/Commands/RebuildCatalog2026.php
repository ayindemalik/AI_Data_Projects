<?php

namespace App\Console\Commands;

use App\Models\Color;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RebuildCatalog2026 extends Command
{
    /**
     * Rebuilds the products table from the cleaned 2026 catalog extract.
     *
     * This is a FULL REBUILD of product data (not an incremental update):
     *   - Optionally wipes existing products + product_colors pivot.
     *   - Loads all 551 catalog products with resolved
     *     category_id / subcategory_id / series_id / color_id, plus
     *     sku, sku_new, kg, palet_adeti.
     *   - Also links each product to its colour via the product_colors pivot
     *     (so the existing many-to-many admin UI keeps working), while the
     *     new color_id column stores the same colour as the "default".
     *
     * Lookup tables (categories, subcategories, series, colors) are left
     * untouched — the catalog references their existing IDs.
     *
     * ALWAYS dry-run first.
     */
    protected $signature = 'catalog:rebuild-2026
                            {--file=storage/app/catalog/products_catalog_2026.json : Path to the resolved product JSON}
                            {--dry-run : Report what would happen without writing}
                            {--fresh : Delete existing products (and product_colors links) before loading}
                            {--show=10 : How many sample rows to show}';

    protected $description = 'Full rebuild of products from the cleaned 2026 catalog extract.';

    public function handle(): int
    {
        $path = $this->option('file');
        if (!str_starts_with($path, '/')) {
            $path = base_path($path);
        }
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            $this->error('Could not parse the JSON file.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $fresh = (bool) $this->option('fresh');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Loaded ' . count($rows) . ' catalog products from ' . basename($path));

        // Validate colour IDs referenced by the catalog exist.
        $colorIds = Color::pluck('id')->flip();
        $badColors = collect($rows)->pluck('color_id')->filter()->unique()
            ->reject(fn ($id) => $colorIds->has($id));
        if ($badColors->isNotEmpty()) {
            $this->warn('Catalog references colour IDs not in the colors table: ' . $badColors->implode(', '));
            $this->warn('Those products will load with color_id = null. Continue anyway, or fix the colors table first.');
        }

        $existingCount = Product::count();
        $this->line("Products currently in the database: {$existingCount}");

        if ($fresh) {
            $this->warn($dryRun
                ? "Would DELETE all {$existingCount} existing products and their colour links."
                : "About to DELETE all {$existingCount} existing products and their colour links.");
            if (!$dryRun && !$this->option('no-interaction')
                && !$this->confirm('This permanently removes existing product data. Proceed?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        $created = 0;
        $withColorPivot = 0;
        $samples = [];

        $work = function () use ($rows, $fresh, $dryRun, $colorIds, &$created, &$withColorPivot, &$samples) {
            if ($fresh && !$dryRun) {
                // Wipe product data only; lookups stay.
                DB::table('product_colors')->delete();
                if (DB::getSchemaBuilder()->hasTable('product_measures')) {
                    DB::table('product_measures')->delete();
                }
                if (DB::getSchemaBuilder()->hasTable('product_variants')) {
                    DB::table('product_variants')->delete();
                }
                if (DB::getSchemaBuilder()->hasTable('product_images')) {
                    DB::table('product_images')->delete();
                }
                Product::query()->forceDelete();
            }

            foreach ($rows as $i => $r) {
                $colorId = ($r['color_id'] ?? null);
                if ($colorId && !$colorIds->has($colorId)) {
                    $colorId = null;
                }

                $name = trim($r['name'] ?? '');
                $attrs = [
                    'category_id'    => $r['category_id'] ?? null,
                    'subcategory_id' => $r['subcategory_id'] ?? null,
                    'series_id'      => $r['series_id'] ?? null,
                    'color_id'       => $colorId,
                    'sku'            => $r['sku'] ?: null,
                    'sku_new'        => $r['sku_new'] ?: null,
                    'kg'             => $r['kg'] ?? null,
                    'palet_adeti'    => $r['palet_adeti'] ?? null,
                    'name'           => ['tr' => $name, 'en' => $name],
                    'description'    => null,
                    'dimensions'     => $this->extractDimensions($name),
                    'status'         => 'active',
                    'catalog_synced_at' => now(),
                    'slug'           => $this->uniqueSlug($name, ($r['sku_new'] ?? $r['sku'] ?? 'p') . '-' . $i),
                ];

                if ($dryRun) {
                    $created++;
                    if (count($samples) < (int) $this->option('show')) {
                        $samples[] = $attrs;
                    }
                    continue;
                }

                $product = Product::create($attrs);
                $created++;

                // Mirror the default colour into the many-to-many pivot.
                if ($colorId) {
                    DB::table('product_colors')->insert([
                        'product_id' => $product->id,
                        'color_id'   => $colorId,
                    ]);
                    $withColorPivot++;
                }
            }
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        // ---------------- report ----------------
        $this->newLine();
        $this->info('=== Result ===');
        $this->table(['Metric', 'Value'], [
            ['Products ' . ($dryRun ? 'to create' : 'created'), $created],
            ['Colour pivot links ' . ($dryRun ? 'to add' : 'added'), $dryRun ? '(skipped in dry run)' : $withColorPivot],
            ['Fresh wipe first', $fresh ? 'yes' : 'no'],
        ]);

        if ($dryRun && $samples) {
            $this->newLine();
            $this->line('Sample of products that would be created:');
            foreach ($samples as $s) {
                $this->line(sprintf('  %-12s cat=%-2s sub=%-3s ser=%-4s col=%-2s kg=%-5s | %s',
                    $s['sku'] ?? $s['sku_new'] ?? '—',
                    $s['category_id'] ?? '—', $s['subcategory_id'] ?? '—',
                    $s['series_id'] ?? '—', $s['color_id'] ?? '—',
                    $s['kg'] ?? '—', mb_substr($s['name']['tr'], 0, 34)));
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run — nothing was written. Re-run with --fresh (without --dry-run) to rebuild.'
            : 'Done. Product catalog rebuilt from the 2026 extract.');

        return self::SUCCESS;
    }

    /**
     * Pull a dimension token like "100x48 cm" from the product name, if present.
     */
    private function extractDimensions(string $name): ?string
    {
        if (preg_match('/(\d{2,3}\s*[xX]\s*\d{2,3})(\s*cm)?/u', $name, $m)) {
            return trim($m[1]) . ' cm';
        }
        return null;
    }

    /**
     * Build a slug that is guaranteed unique AND fits the column's 190-char
     * unique-index limit. The unique part (code + row index) goes FIRST so it
     * can never be truncated away; the human-readable name is appended and
     * capped. Two different products therefore never collide even when their
     * names are long and near-identical (e.g. set-page component rows).
     */
    private function uniqueSlug(string $name, string $code): string
    {
        $prefix = Str::slug($code) ?: 'p';
        $nameSlug = Str::slug($name);
        // Reserve room for the prefix + a separator; cap total at 180 chars
        // (safely under the 190 index limit).
        $room = 180 - strlen($prefix) - 1;
        if ($room > 0 && $nameSlug !== '') {
            $nameSlug = substr($nameSlug, 0, $room);
            return trim($prefix . '-' . $nameSlug, '-');
        }
        return $prefix;
    }
}
