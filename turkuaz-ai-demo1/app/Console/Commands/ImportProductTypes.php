<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportProductTypes extends Command
{
    /**
     * Imports the 29 product types (IDs preserved) and applies the
     * name-analysed product_type_id to matching products.
     *
     * Products are matched by sku_new (falling back to sku) against the
     * pre-computed assignment file. Products with no confident match keep
     * product_type_id = null.
     *
     * Idempotent: updateOrInsert for the lookup rows; assignment simply sets
     * the column, so re-running is safe.
     */
    protected $signature = 'import:product-types
                            {--dir=storage/app/catalog : Directory holding product_types.json and product_type_assignments.json}
                            {--dry-run : Report without writing}';

    protected $description = 'Import product types and assign them to products by analysed product name.';

    public function handle(): int
    {
        $dir = $this->option('dir');
        if (!str_starts_with($dir, '/')) {
            $dir = base_path($dir);
        }

        $typesPath = "{$dir}/product_types.json";
        $assignPath = "{$dir}/product_type_assignments.json";

        foreach ([$typesPath, $assignPath] as $p) {
            if (!file_exists($p)) {
                $this->error("File not found: {$p}");
                return self::FAILURE;
            }
        }

        $types = json_decode(file_get_contents($typesPath), true);
        $assignments = json_decode(file_get_contents($assignPath), true);
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Loaded ' . count($types) . ' product types, ' . count($assignments) . ' product assignments.');

        $created = $assigned = $missing = 0;

        $work = function () use ($types, $assignments, $dryRun, &$created, &$assigned, &$missing) {
            // 1. Lookup rows (IDs preserved).
            foreach ($types as $t) {
                $created++;
                if ($dryRun) {
                    continue;
                }
                DB::table('product_types')->updateOrInsert(
                    ['id' => $t['id']],
                    [
                        'subcategory_id' => $t['subcategory_id'],
                        'name' => json_encode(['tr' => $t['name_tr'], 'en' => $t['name_en']], JSON_UNESCAPED_UNICODE),
                        'slug' => Str::slug($t['name_en']) ?: ('type-' . $t['id']),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 2. Per-product assignment, matched by sku_new then sku.
            foreach ($assignments as $a) {
                $query = Product::query();
                if (!empty($a['sku_new'])) {
                    $query->where('sku_new', $a['sku_new']);
                } elseif (!empty($a['sku'])) {
                    $query->where('sku', $a['sku']);
                } else {
                    continue;
                }

                $product = $query->first();
                if (!$product) {
                    $missing++;
                    continue;
                }

                $assigned++;
                if (!$dryRun) {
                    $product->product_type_id = $a['product_type_id'];
                    $product->saveQuietly();
                }
            }
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);

            // Realign AUTO_INCREMENT past the preserved IDs. This is DDL, so it
            // must stay OUTSIDE the transaction — MySQL implicitly commits on
            // ALTER TABLE, which would leave the wrapping transaction dangling.
            $maxId = DB::table('product_types')->max('id');
            if ($maxId) {
                DB::statement('ALTER TABLE product_types AUTO_INCREMENT = ' . ($maxId + 1));
            }
        }

        $this->newLine();
        $this->info('=== Result ===');
        $this->table(['Metric', 'Value'], [
            ['Product types imported', $created],
            ['Products assigned a type', $assigned],
            ['Assignments with no matching product', $missing],
        ]);

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run — nothing written. Re-run without --dry-run to apply.'
            : 'Done. Products without a confident match keep product_type_id = null.');

        return self::SUCCESS;
    }
}
