<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * SUPERSEDED — intentionally does nothing.
     *
     * Phase 1 scaffolded an empty placeholder `product_colors` (id + timestamps).
     * Phase 7 defined the real pivot in
     * 2026_07_16_105104_create_product_colors_table.php.
     *
     * Left as a no-op rather than deleted: this migration is already recorded
     * in the `migrations` table of running installs, so removing the file would
     * make those installs inconsistent. Emptying it instead means a FRESH
     * database no longer creates the placeholder that made the Phase 7
     * migration fail with "table product_colors already exists".
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        // The table is owned by the Phase 7 migration, which drops it.
    }
};
