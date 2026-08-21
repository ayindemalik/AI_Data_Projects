<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Loads a dump produced by db:export into this machine's database.
 *
 *   php artisan db:import backups/turkuaz_2026-08-21_1030.sql
 *   php artisan db:import backups/turkuaz_2026-08-21_1030.sql.gz
 *
 * This REPLACES the current data. It therefore refuses to run unattended:
 * it names the database, shows what is about to be overwritten, and takes a
 * safety dump first so a bad import is one command away from being undone.
 */
class DatabaseImport extends Command
{
    protected $signature = 'db:import
                            {file : Path to the .sql or .sql.gz dump}
                            {--force : Skip the confirmation prompt (for scripted deploys)}
                            {--no-backup : Do not take a safety dump of the current data first}';

    protected $description = 'Import a database dump, replacing the current data.';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!is_file($file)) {
            // A bare filename is almost always one of ours.
            $candidate = base_path('backups'.DIRECTORY_SEPARATOR.$file);
            if (is_file($candidate)) {
                $file = $candidate;
            } else {
                $this->components->error("File not found: {$file}");

                return self::FAILURE;
            }
        }

        $binary = $this->findBinary('mysql');

        if ($binary === null) {
            $this->components->error('mysql client not found. Set MYSQL_PATH in .env to its full path.');

            return self::FAILURE;
        }

        $db = config('database.connections.mysql.database');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Target database</>', $db.' on '.config('database.connections.mysql.host'));
        $this->components->twoColumnDetail('Dump file', $file.' ('.number_format(filesize($file) / 1048576, 2).' MB)');
        $this->components->twoColumnDetail('Current contents', $this->summarise());

        if (!$this->option('force') && !$this->confirm("This REPLACES the data in \"{$db}\". Continue?", false)) {
            $this->components->warn('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        if (!$this->option('no-backup')) {
            $this->components->info('Taking a safety dump of the current data first …');

            $exit = $this->callSilently('db:export', ['--gzip' => true, '--path' => base_path('backups/pre-import')]);

            if ($exit !== self::SUCCESS) {
                $this->components->error('Safety dump failed — refusing to import. Re-run with --no-backup to override.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Safety dump', base_path('backups/pre-import'));
        }

        $this->components->info('Importing …');

        if (!$this->load($binary, $file)) {
            return self::FAILURE;
        }

        // Config/route caches can hold values that no longer match the data.
        $this->callSilently('cache:clear');
        $this->callSilently('view:clear');

        $this->newLine();
        $this->components->info('Import complete.');
        $this->components->twoColumnDetail('Now contains', $this->summarise());
        $this->newLine();
        $this->line('  <fg=gray>If uploads were included, unzip them into storage/app/public</>');
        $this->line('  <fg=gray>and make sure "php artisan storage:link" has been run.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function load(string $binary, string $file): bool
    {
        $c = config('database.connections.mysql');

        // MYSQL_PWD rather than --password=, which `ps` exposes to every user.
        $process = new Process([
            $binary,
            '--host='.$c['host'],
            '--port='.$c['port'],
            '--user='.$c['username'],
            '--default-character-set=utf8mb4',
            // Two product descriptions carry an embedded base64 image (~65 KB);
            // the default packet size is enough today but not by much.
            '--max-allowed-packet=64M',
            $c['database'],
        ], null, ['MYSQL_PWD' => (string) $c['password']]);

        $process->setTimeout(3600);
        $process->setInput($this->reader($file));
        $process->run();

        if (!$process->isSuccessful()) {
            $this->components->error('Import failed: '.mb_substr(trim($process->getErrorOutput()), 0, 300));

            return false;
        }

        return true;
    }

    /** Streams the dump, transparently decompressing a .gz. */
    private function reader(string $file)
    {
        if (str_ends_with(mb_strtolower($file), '.gz')) {
            return fopen('compress.zlib://'.$file, 'rb');
        }

        return fopen($file, 'rb');
    }

    private function summarise(): string
    {
        try {
            return collect(['products', 'product_images', 'documents', 'users'])
                ->map(fn ($t) => $t.'='.number_format(DB::table($t)->count()))
                ->implode('  ');
        } catch (\Throwable $e) {
            return '(empty or not yet migrated)';
        }
    }

    private function findBinary(string $name): ?string
    {
        if ($configured = env(mb_strtoupper($name).'_PATH')) {
            return is_file($configured) ? $configured : null;
        }

        $candidates = [
            "C:\\Apache24\\mysql\\bin\\{$name}.exe",
            "C:\\xampp\\mysql\\bin\\{$name}.exe",
            "/usr/bin/{$name}",
            "/usr/local/bin/{$name}",
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $which = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', $name]);
        $which->run();

        return $which->isSuccessful() ? trim(strtok($which->getOutput(), "\r\n")) : null;
    }
}
