<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyCatalogImportSeeder extends Seeder
{
    /**
     * Imports Categories, Subcategories, Series, and Colors from the legacy
     * turkuaz6_data.sql dataset, PRESERVING THE ORIGINAL NUMERIC IDs.
     *
     * Why IDs are preserved: the legacy `products` table stores category,
     * sub_category, and collection as plain integer columns (no FK
     * constraints). Keeping the same IDs here means the future Products
     * import can copy those integer columns directly — no lookup/remap step.
     *
     * Safe to re-run: every insert here is an updateOrInsert keyed on 'id',
     * so running this seeder twice never creates duplicates.
     */
    public function run(): void
    {
        $this->importCategories();
        $this->importSubcategories();
        $this->importSeries();
        $this->importColors();
    }

    private function importCategories(): void
    {
        // From legacy `product_category` table.
        $rows = [
            ['id' => 1, 'tr' => 'Seramik Banyo Ürünleri', 'en' => 'Ceramic Bathroom Products'],
            ['id' => 2, 'tr' => 'Gömme Rezervuar', 'en' => 'Concealed Cistern'],
            ['id' => 3, 'tr' => 'Özel Seriler', 'en' => 'Special Series'],
            ['id' => 4, 'tr' => 'Tamamlayıcı Ürünler', 'en' => 'Complementary Products'],
            ['id' => 5, 'tr' => 'Armatürler', 'en' => 'Faucets'],
            ['id' => 6, 'tr' => 'Banyo Duş Sistemleri', 'en' => 'Bathroom Shower Systems'],
        ];

        foreach ($rows as $row) {
            DB::table('categories')->updateOrInsert(
                ['id' => $row['id']],
                [
                    'name' => json_encode(['tr' => $row['tr'], 'en' => $row['en']], JSON_UNESCAPED_UNICODE),
                    'slug' => Str::slug($row['en']),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->syncAutoIncrement('categories');
    }

    private function importSubcategories(): void
    {
        // From legacy `product_subcategory` table. 'category' below is the
        // legacy product_category.id — matches our categories.id exactly.
        $rows = [
            ['id' => 1, 'category' => 1, 'tr' => 'Lavabo', 'en' => 'Washbasin'],
            ['id' => 2, 'category' => 1, 'tr' => 'Klozet', 'en' => 'Toilets'],
            ['id' => 3, 'category' => 1, 'tr' => 'Bide', 'en' => 'Bidet'],
            ['id' => 4, 'category' => 1, 'tr' => 'Ayak', 'en' => 'Pedestals'],
            ['id' => 5, 'category' => 1, 'tr' => 'Hela Taşı', 'en' => 'Squatting Pan'],
            ['id' => 6, 'category' => 1, 'tr' => 'Pisuar / Ara Bölme', 'en' => 'Urinal / Seperator'],
            ['id' => 7, 'category' => 1, 'tr' => 'Seramik Aksesuarlar', 'en' => 'Ceramic Accessories'],
            ['id' => 8, 'category' => 2, 'tr' => 'Alçıpan Tipi', 'en' => 'Drywall Type'],
            ['id' => 9, 'category' => 2, 'tr' => 'Betonarme Tipi', 'en' => 'Concrete Type'],
            ['id' => 10, 'category' => 2, 'tr' => 'Helataşı Tipi', 'en' => 'Squatting Pan Type'],
            ['id' => 11, 'category' => 2, 'tr' => 'Kumanda Panelleri', 'en' => 'Wall Plate'],
            ['id' => 17, 'category' => 4, 'tr' => 'Klozet Kapakları', 'en' => 'Toilet Seats'],
            ['id' => 18, 'category' => 4, 'tr' => 'Rezervuar İç Takımları', 'en' => 'Internal Mechanism'],
            ['id' => 19, 'category' => 4, 'tr' => 'Lavabo Sifonları', 'en' => 'Washbasin Siphons'],
            ['id' => 20, 'category' => 4, 'tr' => 'Lavabo Süzgeçleri', 'en' => 'Washbasin Drainers'],
            ['id' => 21, 'category' => 5, 'tr' => 'Armatürler', 'en' => 'Faucets'],
            ['id' => 22, 'category' => 6, 'tr' => 'Duş Sistemleri', 'en' => 'Shower Systems'],
            ['id' => 23, 'category' => 5, 'tr' => 'Ara Musluk', 'en' => 'Angle Valve'],
            ['id' => 24, 'category' => 5, 'tr' => 'Ankastre Stop Valfi', 'en' => 'Stop Valve'],
            ['id' => 25, 'category' => 4, 'tr' => 'Flexi Bağlantı Elemanları', 'en' => 'Stainless Steel Mesh Connection Hoses'],
            ['id' => 26, 'category' => 6, 'tr' => 'Duş Seti', 'en' => 'Shower Set'],
            ['id' => 30, 'category' => 3, 'tr' => 'Çocuk Banyo Ürünleri', 'en' => 'Kids Bathroom Products'],
            ['id' => 32, 'category' => 3, 'tr' => 'Bedensel Engelli Ürünler', 'en' => 'Disabled Products'],
        ];

        foreach ($rows as $row) {
            DB::table('subcategories')->updateOrInsert(
                ['id' => $row['id']],
                [
                    'category_id' => $row['category'],
                    'name' => json_encode(['tr' => $row['tr'], 'en' => $row['en']], JSON_UNESCAPED_UNICODE),
                    'slug' => Str::slug($row['en']),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->syncAutoIncrement('subcategories');
    }

    private function importSeries(): void
    {
        // From legacy `product_collection` table — this is what our schema
        // calls "Series" (design lines like İbiza, Sharp, Blue). Independent
        // of category, matching the legacy data (its category/sub_category
        // columns were always null).
        $rows = [
            2 => 'İbiza', 3 => 'Arte', 4 => 'Anova', 5 => 'Royal', 6 => '1837',
            7 => 'Frame', 8 => 'Elite', 9 => 'Roma', 11 => 'Porto', 12 => 'Noura',
            13 => 'City', 14 => 'Plus', 16 => 'Yeni Klasik', 17 => 'Nil', 18 => 'One',
            20 => 'Olive', 21 => 'Lal', 22 => 'Lal C', 23 => 'Nova', 24 => 'Olinda',
            25 => 'Zero', 26 => 'Bella', 27 => 'Mona D', 28 => 'Mona', 29 => 'Poco',
            31 => 'Side', 32 => 'Peri', 33 => 'Arda', 34 => 'Mini Köşe', 35 => 'Mini',
            36 => 'Duru', 37 => 'Ova', 38 => 'Lila', 39 => 'Yumurcak', 49 => 'Pinto',
            50 => 'Blue', 51 => 'Kapadokya', 53 => 'Aqua', 54 => 'Defne', 55 => 'Sharp',
            56 => 'Code', 57 => 'Hera', 58 => 'Arya', 59 => 'Harmony', 60 => 'Suit',
            61 => 'Mutfak Eviyesi',
        ];

        // English names, for the rows where the legacy title_en differed meaningfully
        // from the Turkish; everything else reuses the Turkish name (these are mostly
        // proper/brand names anyway, e.g. "İbiza" stays "Ibiza").
        $englishOverrides = [
            'Yeni Klasik' => 'New Classic',
            'Mini Köşe' => 'Mini Corner',
            'Mutfak Eviyesi' => 'Kitchen Sink',
        ];

        foreach ($rows as $id => $trName) {
            $enName = $englishOverrides[$trName] ?? str_replace('İ', 'I', $trName);

            DB::table('series')->updateOrInsert(
                ['id' => $id],
                [
                    'name' => json_encode(['tr' => $trName, 'en' => $enName], JSON_UNESCAPED_UNICODE),
                    'description' => null,
                    'slug' => Str::slug($enName),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->syncAutoIncrement('series');
    }

    private function importColors(): void
    {
        // The first two (id 1, 2) come from the legacy `product_colors` table.
        // The rest come from the CeraStyle demo's color palette (Tukuraz_Seramik
        // Yapay_Zeka_Botu_Demo.jsx) — they have no legacy numeric ID to preserve,
        // so they're appended after via plain insert (auto-increment continues
        // safely from the highest existing ID).
        $legacyRows = [
            ['id' => 1, 'tr' => 'Beyaz', 'en' => 'White', 'hex' => '#FFFFFF'],
            ['id' => 2, 'tr' => 'Siyah', 'en' => 'Black', 'hex' => '#000000'],
        ];

        foreach ($legacyRows as $row) {
            DB::table('colors')->updateOrInsert(
                ['id' => $row['id']],
                [
                    'name' => json_encode(['tr' => $row['tr'], 'en' => $row['en']], JSON_UNESCAPED_UNICODE),
                    'hex_value' => $row['hex'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Demo palette — hex values are close approximations (matte/gloss finishes
        // don't have one "true" hex); adjust freely in the admin UI later.
        $demoRows = [
            ['tr' => 'Mat Siyah', 'en' => 'Matte Black', 'hex' => '#1C1C1C'],
            ['tr' => 'Mat Beyaz', 'en' => 'Matte White', 'hex' => '#F5F5F0'],
            ['tr' => 'Mat Gri', 'en' => 'Matte Grey', 'hex' => '#8C8C8C'],
            ['tr' => 'Parlak Siyah', 'en' => 'Glossy Black', 'hex' => '#0A0A0A'],
            ['tr' => 'Mat Kapuçino', 'en' => 'Matte Cappuccino', 'hex' => '#C8A27A'],
        ];

        foreach ($demoRows as $row) {
            $exists = DB::table('colors')
                ->whereRaw("JSON_EXTRACT(name, '$.tr') = ?", [$row['tr']])
                ->exists();

            if (!$exists) {
                DB::table('colors')->insert([
                    'name' => json_encode(['tr' => $row['tr'], 'en' => $row['en']], JSON_UNESCAPED_UNICODE),
                    'hex_value' => $row['hex'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * After inserting rows with explicit IDs, MySQL's AUTO_INCREMENT counter
     * needs to be bumped past the highest ID we just used, so future
     * admin-created rows (via the CRUD UI) don't collide with legacy IDs.
     */
    private function syncAutoIncrement(string $table): void
    {
        $maxId = DB::table($table)->max('id');

        if ($maxId) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($maxId + 1));
        }
    }
}
