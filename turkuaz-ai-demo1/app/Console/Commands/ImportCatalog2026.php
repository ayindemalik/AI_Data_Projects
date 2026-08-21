<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCatalog2026 extends Command
{
    /**
     * Applies the 2026 price-list data (extracted from the PDF) to existing products.
     *
     * Matching: catalog ESKI KOD  ->  products.sku
     * Updating: sku_new (YENI KOD), kg, palet_adeti, catalog_synced_at
     *
     * Nothing is overwritten destructively: sku (the legacy code) is never
     * touched, so dealer searches on old codes keep working.
     *
     * ALWAYS run with --dry-run first and read the report.
     */
    protected $signature = 'import:catalog-2026
                            {--file=code_mapping.json : Path to the extracted JSON (relative to project root or absolute)}
                            {--dry-run : Report what would change without writing anything}
                            {--show=15 : How many unmatched rows to list in the report}';

    protected $description = 'Update products with YENI KOD, KG and Palet Adeti from the 2026 catalog extract.';

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
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Loaded ' . count($rows) . ' catalog rows from ' . basename($path));

        // Index products by sku once, rather than querying per row.
        $bySku = Product::whereNotNull('sku')->get(['id', 'sku', 'sku_new', 'kg', 'palet_adeti'])
            ->keyBy(fn ($p) => $this->normalizeCode($p->sku));

        $this->line('Products with a SKU in the database: ' . $bySku->count());
        $this->newLine();

        $matched = $updated = $unchanged = 0;
        $noEskiKod = 0;
        $unmatched = [];
        $conflicts = [];

        $work = function () use ($rows, $bySku, $dryRun, &$matched, &$updated, &$unchanged, &$noEskiKod, &$unmatched, &$conflicts) {
            foreach ($rows as $row) {
                $eski = trim((string) ($row['eski_kod'] ?? ''));

                if ($eski === '') {
                    $noEskiKod++;                       // new product, no legacy code to match on
                    continue;
                }

                $product = $bySku->get($this->normalizeCode($eski));

                if (!$product) {
                    $unmatched[] = $row;
                    continue;
                }

                $matched++;

                $newSku = trim((string) ($row['yeni_kod'] ?? '')) ?: null;
                $kg     = $this->toDecimal($row['kg'] ?? null);
                $palet  = $this->toInt($row['palet_adeti'] ?? null);

                // Flag (but do not silently overwrite) a different new code already present.
                if ($product->sku_new && $newSku && $product->sku_new !== $newSku) {
                    $conflicts[] = [
                        'sku' => $product->sku,
                        'existing' => $product->sku_new,
                        'incoming' => $newSku,
                    ];
                    continue;
                }

                $changes = array_filter([
                    'sku_new'     => $newSku !== null && $product->sku_new !== $newSku ? $newSku : null,
                    'kg'          => $kg !== null && (string) $product->kg !== (string) $kg ? $kg : null,
                    'palet_adeti' => $palet !== null && $product->palet_adeti !== $palet ? $palet : null,
                ], fn ($v) => $v !== null);

                if (empty($changes)) {
                    $unchanged++;
                    continue;
                }

                $updated++;

                if (!$dryRun) {
                    $changes['catalog_synced_at'] = now();
                    Product::whereKey($product->id)->update($changes);
                }
            }
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        // ---------------- report ----------------
        $this->info('=== Result ===');
        $this->table(['Outcome', 'Rows'], [
            ['Matched on ESKI KOD -> sku', $matched],
            ['  of which updated', $updated],
            ['  of which already current', $unchanged],
            ['Skipped (no ESKI KOD in catalog)', $noEskiKod],
            ['Unmatched (no product with that sku)', count($unmatched)],
            ['Conflicts (different sku_new already set)', count($conflicts)],
        ]);

        $limit = (int) $this->option('show');

        if ($unmatched) {
            $this->newLine();
            $this->warn('Unmatched catalog rows (first ' . min($limit, count($unmatched)) . '):');
            foreach (array_slice($unmatched, 0, $limit) as $r) {
                $this->line(sprintf('  %-16s %-22s %s',
                    $r['eski_kod'] ?? '', $r['yeni_kod'] ?? '',
                    mb_substr($r['product_name'] ?? '', 0, 50)));
            }
            $this->line('  These are catalog products with no matching sku in the database —');
            $this->line('  either genuinely new, or the legacy import used a different code format.');
        }

        if ($conflicts) {
            $this->newLine();
            $this->warn('Conflicts (existing sku_new differs from the catalog):');
            foreach (array_slice($conflicts, 0, $limit) as $c) {
                $this->line(sprintf('  sku %-14s existing:%-22s incoming:%s',
                    $c['sku'], $c['existing'], $c['incoming']));
            }
            $this->line('  Left untouched. Resolve manually, or clear sku_new on those rows and re-run.');
        }

        $this->newLine();
        if ($dryRun) {
            $this->info('Dry run — nothing was written. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Updated products carry a catalog_synced_at timestamp.');
        }

        return self::SUCCESS;
    }

    /**
     * Codes are compared case-insensitively with surrounding whitespace removed.
     * Nothing else is stripped: '036500-u' and '036500-u97' are DIFFERENT products
     * (base vs colour variant), so punctuation must be preserved.
     */
    private function normalizeCode(?string $code): string
    {
        return mb_strtolower(trim((string) $code));
    }

    private function toDecimal($v): ?float
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        return (float) str_replace(',', '.', $v);
    }

    private function toInt($v): ?int
    {
        $v = trim((string) $v);
        return $v === '' ? null : (int) $v;
    }
}
