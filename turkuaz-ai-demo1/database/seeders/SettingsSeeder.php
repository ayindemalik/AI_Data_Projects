<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Sets sensible defaults the first time this runs. Safe to re-run —
     * Setting::set() uses updateOrCreate, so it never duplicates rows.
     */
    public function run(): void
    {
        Setting::set('default_locale', 'tr', 'string');
        Setting::set('assistant_enabled', '1', 'boolean');
        Setting::set('dealer_contact_note', 'Fiyat ve teklif bilgisi için lütfen size en yakın bayimizle iletişime geçin.', 'text');
    }
}
