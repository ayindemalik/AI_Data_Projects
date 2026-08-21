<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeLegacyMedia extends Command
{
    /**
     * Restores images and descriptions from the legacy database onto the
     * rebuilt 2026 products, matching on sku (exact, then base-code so a
     * colour-variant like 036500-u97 inherits the base 036500 image).
     *
     * For products with no legacy match, sets a shared placeholder image so
     * every product has something to show.
     *
     * Idempotent: clears a product's existing images before re-adding, so
     * re-running doesn't create duplicates.
     *
     * Legacy image/gallery URLs are absolute CDN links, stored as-is (the
     * existing ProductImage::getUrlAttribute returns absolute URLs unchanged).
     */
    protected $signature = 'catalog:merge-media
                            {--file=storage/app/catalog/legacy_media_merge.json : Resolved media JSON}
                            {--placeholder=/images/placeholder-product.svg : Public path to the default image}
                            {--dry-run : Report without writing}';

    protected $description = 'Restore legacy images/descriptions onto rebuilt products; placeholder for the rest.';

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

        $merge = json_decode(file_get_contents($path), true);
        if (!is_array($merge)) {
            $this->error('Could not parse the media JSON.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $placeholder = $this->option('placeholder');


        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Loaded media for ' . count($merge) . ' SKUs from ' . basename($path));

        $withRealImage = 0;
        $withPlaceholder = 0;
        $descUpdated = 0;
        $galleryAdded = 0;

        $work = function () use ($merge, $placeholder, $dryRun, &$withRealImage, &$withPlaceholder, &$descUpdated, &$galleryAdded) {
            Product::query()->select('id', 'sku', 'description')->chunkById(200, function ($products) use (
                $merge, $placeholder, $dryRun, &$withRealImage, &$withPlaceholder, &$descUpdated, &$galleryAdded
            ) {
                foreach ($products as $product) {
                    $data = $product->sku ? ($merge[$product->sku] ?? null) : null;

                    if (!$dryRun) {
                        // Reset images for a clean, idempotent re-run.
                        ProductImage::where('product_id', $product->id)->delete();
                    }

                    if ($data && !empty($data['image'])) {
                        $withRealImage++;

                        if (!$dryRun) {
                            // Main image first (sort_order 0).
$this->makeImage($product->id, $data['image'], 0);

                            // Gallery images after.
                            $order = 1;
                            foreach (($data['gallery'] ?? []) as $g) {
                                if ($g && $g !== $data['image']) {
                                    $this->makeImage($product->id, $g, $order++);
                                    $galleryAdded++;
                                }
                            }
                        } else {
                            $galleryAdded += count(array_filter($data['gallery'] ?? []));
                        }

                        // Descriptions, if the rebuilt product has none.
                        if (!empty($data['desc_tr'])) {
                            $descUpdated++;
                            if (!$dryRun) {
                                $product->description = [
                                    'tr' => $data['desc_tr'],
                                    'en' => $data['desc_en'] ?: $data['desc_tr'],
                                ];
                                $product->save();
                            }
                        }
                    } else {
                        // No legacy image -> placeholder.
                        $withPlaceholder++;
                        if (!$dryRun) {
                            $this->makeImage($product->id, $placeholder, 0);
                        }
                    }
                }
            });
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        $this->newLine();
        $this->info('=== Result ===');
        $this->table(['Metric', 'Value'], [
            ['Products with restored real image', $withRealImage],
            ['Products with placeholder image', $withPlaceholder],
            ['Descriptions restored', $descUpdated],
            ['Gallery images added', $galleryAdded],
        ]);

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run — nothing written. Re-run without --dry-run to apply.'
            : 'Done. Every product now has at least a placeholder image.');

        return self::SUCCESS;
    }

    private ?bool $hasSortOrder = null;
    private ?string $imageColumn = null;

    /**
     * Insert a product image row. Detects the real column names on this install:
     * the image URL column may be 'path', 'image', 'url', or 'file'; sort_order
     * may be absent. Writes directly via the query builder to bypass model
     * fillable/guarded differences.
     */
    private function makeImage(int $productId, string $image, int $order): void
    {
        if ($this->imageColumn === null) {
            foreach (['path', 'image', 'url', 'file', 'image_path'] as $candidate) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('product_images', $candidate)) {
                    $this->imageColumn = $candidate;
                    break;
                }
            }
            $this->imageColumn = $this->imageColumn ?: 'path';
        }
        if ($this->hasSortOrder === null) {
            $this->hasSortOrder = \Illuminate\Support\Facades\Schema::hasColumn('product_images', 'sort_order');
        }

        $row = [
            'product_id' => $productId,
            $this->imageColumn => $image,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($this->hasSortOrder) {
            $row['sort_order'] = $order;
        }
        \Illuminate\Support\Facades\DB::table('product_images')->insert($row);
    }
}
