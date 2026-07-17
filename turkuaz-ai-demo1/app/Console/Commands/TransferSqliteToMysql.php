<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransferSqliteToMysql extends Command
{
    /**
     * php artisan db:transfer-sqlite-to-mysql --sqlite-path=database/database.sqlite.backup_TIMESTAMP
     */
    protected $signature = 'app:transfer-sqlite-to-mysql
                            {--sqlite-path= : database/database.sqlite}
                            {--mysql-connection=mysql : Destination connection name (as defined in config/database.php)}
                            {--chunk=500 : Rows per chunk when copying large tables}';

    protected $description = 'Copy all table data from a SQLite file into the destination MySQL connection. Run migrate on MySQL first so the target tables/columns already exist.';

    public function handle(): int
    {
        $sqlitePath = $this->option('sqlite-path');
        $mysqlConn = $this->option('mysql-connection');
        $chunkSize = (int) $this->option('chunk');

        if (!$sqlitePath) {
            $this->error('You must pass --sqlite-path=path/to/database.sqlite');
            return self::FAILURE;
        }

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite file not found: {$sqlitePath}");
            return self::FAILURE;
        }

        // Register a throwaway connection pointing at the given SQLite file.
        // This avoids touching config/database.php or the live DB_* env values.
        config(['database.connections.sqlite_transfer_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        $sqliteConn = 'sqlite_transfer_source';

        // List every user table in the SQLite file (skip SQLite's internal tables).
        $tables = DB::connection($sqliteConn)
            ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        if (empty($tables)) {
            $this->warn('No tables found in the source SQLite file. Nothing to transfer.');
            return self::SUCCESS;
        }

        DB::connection($mysqlConn)->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $tableRow) {
            $table = $tableRow->name;

            if (!Schema::connection($mysqlConn)->hasTable($table)) {
                $this->warn("Skipping [{$table}] — no matching table in MySQL. Run migrations first.");
                continue;
            }

            $rowCount = DB::connection($sqliteConn)->table($table)->count();

            if ($rowCount === 0) {
                $this->line("Skipping [{$table}] — source table is empty.");
                continue;
            }

            $this->info("Copying [{$table}] — {$rowCount} row(s)...");

            // Clear any existing rows in the destination table before inserting fresh data.
            DB::connection($mysqlConn)->table($table)->truncate();

            if (Schema::connection($sqliteConn)->hasColumn($table, 'id')) {
                // Chunk by id for tables with a primary key, to keep memory usage low.
                DB::connection($sqliteConn)->table($table)->orderBy('id')
                    ->chunk($chunkSize, function ($rows) use ($mysqlConn, $table) {
                        $data = $rows->map(fn ($row) => (array) $row)->all();
                        if (!empty($data)) {
                            DB::connection($mysqlConn)->table($table)->insert($data);
                        }
                    });
            } else {
                // No 'id' column to order by — pull the whole table and insert in manual chunks.
                $rows = DB::connection($sqliteConn)->table($table)->get()
                    ->map(fn ($row) => (array) $row)->all();

                foreach (array_chunk($rows, $chunkSize) as $chunk) {
                    DB::connection($mysqlConn)->table($table)->insert($chunk);
                }
            }
        }

        DB::connection($mysqlConn)->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Transfer complete.');
        return self::SUCCESS;
    }
}
