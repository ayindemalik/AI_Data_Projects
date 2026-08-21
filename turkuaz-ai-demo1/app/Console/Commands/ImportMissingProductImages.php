<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMissingProductImages extends Command
{
    /**
     * Fills in missing product images from a legacy phpMyAdmin JSON export
     * (the "Export to JSON plugin for PHPMyAdmin" format, with a header /
     * database / table envelope around the rows).
     *
     * For every row in the export:
     *   1. match legacy products.product_code -> products.sku (trimmed, case-insensitive)
     *   2. if that product already has any product_images row, skip it
     *   3. otherwise insert product_images(product_id, path = legacy image URL, sort_order 0)
     *
     * Legacy image values are absolute CDN/site URLs and are stored as-is —
     * ProductImage::getUrlAttribute() returns absolute URLs unchanged.
     *
     * Idempotent: a product that receives an image in one run is skipped by
     * rule 2 on the next.
     *
     * Note on placeholders: catalog:merge-media gives every image-less product
     * a shared placeholder row, which counts as "has an image" under rule 2.
     * Pass --only-placeholder to treat those products as image-less, replacing
     * the placeholder with the legacy image. The summary always reports how
     * many products were skipped for that reason, so a strict run tells you
     * whether the flag is worth using.
     */
    protected $signature = 'products:import-missing-images
                            {--file=../../products.json : phpMyAdmin JSON export (absolute, or relative to the app root)}
                            {--table=products : Table name to read inside the export}
                            {--placeholder=/images/placeholder-product.svg : Path that identifies a placeholder image row}
                            {--only-placeholder : Also fill products whose only image is the placeholder (replaces it)}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Add product_images rows from a legacy JSON export for products that have no image yet.';

    public function handle(): int
    {
        $path = $this->resolvePath($this->option('file'));

        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readExport($path, $this->option('table'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $onlyPlaceholder = (bool) $this->option('only-placeholder');
        $placeholder = $this->option('placeholder');

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Read ' . count($rows) . ' rows from ' . basename($path));

        // --- Preload the DB side so the loop below costs no queries ---

        // Normalised sku -> product id. sku is unique, and MySQL's default
        // collation is case-insensitive, so upper-casing cannot collide.
        $skuToId = [];
        foreach (DB::table('products')->whereNotNull('sku')->pluck('sku', 'id') as $id => $sku) {
            $skuToId[strtoupper(trim($sku))] = $id;
        }

        // Products that already have at least one image row, and the subset
        // whose images are all placeholders.
        $withAnyImage = DB::table('product_images')->distinct()->pluck('product_id')->flip();
        $withRealImage = DB::table('product_images')
            ->where('path', '!=', $placeholder)
            ->distinct()->pluck('product_id')->flip();

        $insert = [];          // rows to write
        $replaceIds = [];      // products whose placeholder row is being replaced
        $seenCodes = [];       // first-wins dedupe across the export
        $warnings = [];

        $noCode = 0;
        $noMatch = 0;
        $noImage = 0;
        $hasImage = 0;
        $placeholderSkipped = 0;

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['product_code'] ?? '')));

            if ($code === '') {
                $noCode++;
                continue;
            }

            if (isset($seenCodes[$code])) {
                $warnings[] = "Duplicate product_code '{$code}' in the export — later occurrence ignored";
                continue;
            }
            $seenCodes[$code] = true;

            if (!isset($skuToId[$code])) {
                $noMatch++;
                continue;
            }

            $productId = $skuToId[$code];
            $replacesPlaceholder = false;

            if (isset($withAnyImage[$productId])) {
                // Already has an image. Only a placeholder-only product is
                // eligible, and only when the flag asks for it.
                if (isset($withRealImage[$productId])) {
                    $hasImage++;
                    continue;
                }

                if (!$onlyPlaceholder) {
                    $placeholderSkipped++;
                    continue;
                }

                $replacesPlaceholder = true;
            }

            $image = trim((string) ($row['image'] ?? ''));

            if ($image === '') {
                $noImage++;
                continue;
            }

            if ($replacesPlaceholder) {
                $replaceIds[] = $productId;
            }

            $insert[] = [
                'product_id' => $productId,
                'path' => $image,
                'alt_text' => null,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!$dryRun && $insert !== []) {
            DB::transaction(function () use ($insert, $replaceIds, $placeholder) {
                if ($replaceIds !== []) {
                    DB::table('product_images')
                        ->whereIn('product_id', $replaceIds)
                        ->where('path', $placeholder)
                        ->delete();
                }

                foreach (array_chunk($insert, 200) as $chunk) {
                    DB::table('product_images')->insert($chunk);
                }
            });
        }

        // --- Report ---

        $this->newLine();
        $this->info('=== Result ===');
        $this->table(['Outcome', 'Rows'], [
            ['Images added', count($insert)],
            ['  of which replaced a placeholder', count($replaceIds)],
            ['Skipped — product already has an image', $hasImage],
            ['Skipped — only image is the placeholder', $placeholderSkipped],
            ['Skipped — no product with that sku', $noMatch],
            ['Skipped — no product_code in the export', $noCode],
            ['Skipped — no image in the export row', $noImage],
        ]);

        foreach (array_slice($warnings, 0, 20) as $warning) {
            $this->warn($warning);
        }
        if (count($warnings) > 20) {
            $this->warn('... and ' . (count($warnings) - 20) . ' more duplicate codes.');
        }

        $this->newLine();

        if ($placeholderSkipped > 0 && !$onlyPlaceholder) {
            $this->comment("{$placeholderSkipped} matched products carry only the placeholder image. "
                . 'Re-run with --only-placeholder to replace those with the legacy image.');
        }

        $this->info($dryRun
            ? 'Dry run — nothing written. Re-run without --dry-run to apply.'
            : 'Done.');

        return self::SUCCESS;
    }

    /**
     * Absolute paths (POSIX or Windows drive-letter) are used as-is;
     * anything else resolves against the application root.
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Pulls the requested table's rows out of the phpMyAdmin export envelope:
     * [ {type:header}, {type:database}, {type:table, name:..., data:[ ... ]} ].
     * A plain array of row objects is also accepted.
     */
    private function readExport(string $path, string $table): ?array
    {
        $json = json_decode(file_get_contents($path), true);

        if (!is_array($json)) {
            $this->error('Could not parse the JSON: ' . json_last_error_msg());

            return null;
        }

        foreach ($json as $entry) {
            if (is_array($entry) && ($entry['type'] ?? null) === 'table' && ($entry['name'] ?? null) === $table) {
                if (!is_array($entry['data'] ?? null)) {
                    $this->error("Table '{$table}' in the export has no data array.");

                    return null;
                }

                return $entry['data'];
            }
        }

        // Not an export envelope — accept a bare list of rows.
        if (isset($json[0]['product_code'])) {
            return $json;
        }

        $this->error("No '{$table}' table found in the export. Use --table to name the right one.");

        return null;
    }
}
