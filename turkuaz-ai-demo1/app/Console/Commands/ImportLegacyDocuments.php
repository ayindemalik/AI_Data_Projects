<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyDocuments extends Command
{
    /**
     * Imports from the legacy dump (loaded into a temp MySQL DB):
     *
     *   legacy document_category            -> document_categories (IDs 5-12 preserved)
     *   legacy documents                    -> documents (IDs preserved, type='general',
     *                                          bilingual file {tr, en} kept as CDN URLs)
     *   legacy products doc columns         -> documents rows attached to the product:
     *     datasheet_doc -> type 'datasheet'     2d_doc -> '2d'      3d_doc -> '3d'
     *     setup_doc     -> type 'setup'         warranty_doc -> 'warranty'
     *     service_doc   -> type 'service'       spare_part   -> 'spare_part'
     *
     * Per-product docs have no legacy ID (they were columns), so they're
     * plain inserts deduplicated on (product_id, type, file value).
     * Safe to re-run.
     */
    protected $signature = 'import:legacy-documents
                            {--legacy-db=turkuaz_legacy : Name of the temporary MySQL database holding the legacy dump}';

    protected $description = 'Import document categories, corporate documents, and per-product document links from the legacy dump.';

    public function handle(): int
    {
        config(['database.connections.legacy_import' => array_merge(
            config('database.connections.mysql'),
            ['database' => $this->option('legacy-db')]
        )]);

        $legacy = DB::connection('legacy_import');

        // ---- 1. Document categories (IDs preserved) ----
        $categories = $legacy->table('document_category')->orderBy('id')->get();

        foreach ($categories as $cat) {
            DB::table('document_categories')->updateOrInsert(
                ['id' => $cat->id],
                [
                    'name' => json_encode(['tr' => trim($cat->title_tr), 'en' => trim($cat->title_en)], JSON_UNESCAPED_UNICODE),
                    'slug' => Str::slug(trim($cat->title_en)) ?: 'category-' . $cat->id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $this->syncAutoIncrement('document_categories');
        $this->info("Imported {$categories->count()} document categories.");

        // ---- 2. Corporate documents (IDs preserved) ----
        $docs = $legacy->table('documents')->orderBy('id')->get();

        foreach ($docs as $doc) {
            DB::table('documents')->updateOrInsert(
                ['id' => $doc->id],
                [
                    'document_category_id' => $doc->category,
                    'product_id' => null,
                    'type' => 'general',
                    'title' => json_encode([
                        'tr' => trim($doc->title_tr),
                        'en' => trim((string) $doc->title_en) ?: trim($doc->title_tr),
                    ], JSON_UNESCAPED_UNICODE),
                    'file' => json_encode([
                        'tr' => trim($doc->file_tr) ?: null,
                        'en' => trim((string) $doc->file_en) ?: null,
                    ]),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $this->syncAutoIncrement('documents');
        $this->info("Imported {$docs->count()} corporate documents.");

        // ---- 3. Per-product technical documents (from legacy products columns) ----
        $columnTypeMap = [
            'datasheet_doc' => 'datasheet',
            '2d_doc' => '2d',
            '3d_doc' => '3d',
            'setup_doc' => 'setup',
            'warranty_doc' => 'warranty',
            'service_doc' => 'service',
            'spare_part' => 'spare_part',
        ];

        $typeTitles = [
            'datasheet' => ['tr' => 'Teknik Föy', 'en' => 'Datasheet'],
            '2d' => ['tr' => '2D Çizim', 'en' => '2D Drawing'],
            '3d' => ['tr' => '3D Çizim', 'en' => '3D Drawing'],
            'setup' => ['tr' => 'Montaj Kılavuzu', 'en' => 'Installation Guide'],
            'warranty' => ['tr' => 'Garanti Belgesi', 'en' => 'Warranty Document'],
            'service' => ['tr' => 'Servis Dokümanı', 'en' => 'Service Document'],
            'spare_part' => ['tr' => 'Yedek Parça', 'en' => 'Spare Parts'],
        ];

        $productDocCount = 0;

        foreach ($legacy->table('products')->orderBy('id')->get() as $row) {
            if (!DB::table('products')->where('id', $row->id)->exists()) {
                continue; // Product wasn't imported — nothing to attach to.
            }

            foreach ($columnTypeMap as $column => $type) {
                $url = trim((string) ($row->{$column} ?? ''));

                // Legacy columns hold '', NULL, or a URL; only real URLs count.
                if ($url === '' || !Str::startsWith($url, ['http://', 'https://'])) {
                    continue;
                }

                $exists = DB::table('documents')
                    ->where('product_id', $row->id)
                    ->where('type', $type)
                    ->whereRaw("JSON_EXTRACT(file, '$.tr') = ?", [$url])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('documents')->insert([
                    'document_category_id' => null,
                    'product_id' => $row->id,
                    'type' => $type,
                    'title' => json_encode($typeTitles[$type], JSON_UNESCAPED_UNICODE),
                    // Legacy per-product docs are single files; stored under 'tr'
                    // and served to both locales by Document::fileUrl()'s fallback.
                    'file' => json_encode(['tr' => $url, 'en' => null]),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $productDocCount++;
            }
        }

        $this->info("Imported {$productDocCount} per-product technical documents.");
        $this->info('Done. Legacy IDs preserved for categories and corporate documents.');

        return self::SUCCESS;
    }

    private function syncAutoIncrement(string $table): void
    {
        $maxId = DB::table($table)->max('id');

        if ($maxId) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 1));
        }
    }
}
