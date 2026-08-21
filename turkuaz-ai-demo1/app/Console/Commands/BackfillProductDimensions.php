<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Fills products.dimensions from the size written in the product name.
 *
 * The catalogue names its sizes but does not always store them:
 *
 *   "Aqua Tezgahüstü Lavabo 55x42 cm (Duvara Sıfır Lavabo)" -> "55x42 cm"
 *   "Bella Rimless Asma Klozet 48 cm"                       -> "48 cm"
 *
 * Only rows whose name actually carries a size are touched, and by default
 * only when dimensions is still empty — an existing value was either imported
 * or corrected by hand and outranks anything parsed out of a title.
 *
 *   php artisan products:backfill-dimensions --dry-run
 */
class BackfillProductDimensions extends Command
{
    protected $signature = 'products:backfill-dimensions
                            {--dry-run : Report what would change without writing}
                            {--overwrite : Also replace dimensions that are already set}';

    protected $description = 'Set products.dimensions from the size named in each product title.';

    /** 55x42, 65 x 32 x 20,5 — decimals appear with a Turkish comma. */
    private const PAIR = '/(\d{1,4}(?:[.,]\d{1,2})?)\s*[xX×]\s*(\d{1,4}(?:[.,]\d{1,2})?)(?:\s*[xX×]\s*(\d{1,4}(?:[.,]\d{1,2})?))?\s*(cm|mm)?/u';

    /** A lone "48 cm". The unit is required — a bare number names nothing. */
    private const SINGLE = '/(?<![\dxX×])(\d{1,4}(?:[.,]\d{1,2})?)\s*(cm|mm)\b/u';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $updated = $skipped = $unchanged = $noSize = 0;
        $samples = [];

        foreach (Product::cursor() as $product) {
            $name = $product->name['tr'] ?? $product->name['en'] ?? '';
            $parsed = $this->parse($name);

            if ($parsed === null) {
                $noSize++;
                continue;
            }

            $current = trim((string) $product->dimensions);

            if ($current !== '' && !$overwrite) {
                $skipped++;
                continue;
            }

            if ($current === $parsed) {
                $unchanged++;
                continue;
            }

            if (count($samples) < 15) {
                $samples[] = [$product->id, mb_strimwidth($name, 0, 46, '..'), $current ?: '-', $parsed];
            }

            if (!$dryRun) {
                $product->update(['dimensions' => $parsed]);
            }

            $updated++;
        }

        $this->newLine();

        if ($samples !== []) {
            $this->table(['ID', 'Name', 'Was', 'Becomes'], $samples);

            if ($updated > count($samples)) {
                $this->line('  ... and '.($updated - count($samples)).' more.');
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Updated</>', (string) $updated);
        $this->components->twoColumnDetail('Already correct', (string) $unchanged);
        $this->components->twoColumnDetail('Kept (dimensions already set)', (string) $skipped);
        $this->components->twoColumnDetail('No size in the name', (string) $noSize);
        $this->newLine();

        if ($dryRun) {
            $this->components->warn('Dry run — nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * The size named in a title, normalised to the catalogue's own format
     * ("60x45 cm"), or null when the title names none.
     */
    private function parse(string $name): ?string
    {
        if (preg_match(self::PAIR, $name, $m)) {
            $parts = array_values(array_filter([$m[1], $m[2], $m[3] ?? ''], fn ($p) => $p !== ''));

            return implode('x', $parts).' '.($m[4] ?? 'cm');
        }

        if (preg_match(self::SINGLE, $name, $m)) {
            return $m[1].' '.$m[2];
        }

        return null;
    }
}
