<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Dumps the project database to a file ready to move between machines.
 *
 *   php artisan db:export                 -> backups/turkuaz_2026-08-21_1030.sql
 *   php artisan db:export --gzip          -> ...sql.gz  (phpMyAdmin accepts this too)
 *   php artisan db:export --with-uploads  -> also zips storage/app/public
 *
 * The dump is deliberately phpMyAdmin-friendly: no CREATE DATABASE / USE lines,
 * so it imports into whatever database is already selected there. Session and
 * cache tables ship as structure only — their contents belong to the machine
 * that made them, and copying local sessions onto the server logs everyone out.
 */
class DatabaseExport extends Command
{
    protected $signature = 'db:export
                            {--gzip : Compress the dump (smaller upload; phpMyAdmin reads .sql.gz)}
                            {--with-uploads : Also archive storage/app/public}
                            {--path= : Write here instead of backups/}';

    protected $description = 'Dump the database (and optionally uploads) for transfer to another server.';

    /** Machine-local data: schema is kept, rows are not. */
    private const TRANSIENT = [
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        $binary = $this->findBinary('mysqldump');

        if ($binary === null) {
            $this->components->error('mysqldump not found. Set MYSQLDUMP_PATH in .env to its full path.');

            return self::FAILURE;
        }

        $db = config('database.connections.mysql.database');
        $dir = $this->option('path') ?: base_path('backups');
        File::ensureDirectoryExists($dir);

        $file = rtrim($dir, '/\\').DIRECTORY_SEPARATOR
            .'turkuaz_'.now()->format('Y-m-d_Hi').'.sql';

        $this->components->info("Exporting {$db} …");

        // Pass 1: everything except the transient tables.
        $ignore = [];
        foreach (self::TRANSIENT as $table) {
            $ignore[] = "--ignore-table={$db}.{$table}";
        }

        if (!$this->dump($binary, array_merge($this->baseFlags(), $ignore), [], $file, false)) {
            return self::FAILURE;
        }

        // Pass 2: the transient tables' structure only, appended. Table names
        // come after the database name, not instead of it.
        $present = array_values(array_intersect(self::TRANSIENT, $this->existingTables()));

        if ($present !== [] && !$this->dump(
            $binary,
            array_merge($this->baseFlags(), ['--no-data']),
            $present,
            $file,
            true
        )) {
            return self::FAILURE;
        }

        $this->components->twoColumnDetail('SQL dump', $this->describe($file));

        if ($this->option('gzip')) {
            $file = $this->gzip($file);
            $this->components->twoColumnDetail('Compressed', $this->describe($file));
        }

        if ($this->option('with-uploads')) {
            $this->archiveUploads($dir);
        }

        $this->newLine();
        $this->line('  <fg=gray>Next: copy the file to the server, then either</>');
        $this->line('  <fg=gray>  php artisan db:import '.basename($file).'</>');
        $this->line('  <fg=gray>  or import it through phpMyAdmin (select the database first).</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * utf8mb4 is not optional here: without it Turkish characters come back as
     * mojibake. --no-tablespaces avoids needing the PROCESS privilege, which
     * shared hosting rarely grants.
     */
    private function baseFlags(): array
    {
        $c = config('database.connections.mysql');

        // The password goes through MYSQL_PWD, not --password=: a command line
        // is visible to every user on the box via `ps`.
        return [
            '--host='.$c['host'],
            '--port='.$c['port'],
            '--user='.$c['username'],
            '--default-character-set=utf8mb4',
            '--single-transaction',      // consistent snapshot, no table locks
            '--no-tablespaces',
            '--hex-blob',
            '--routines',
            '--triggers',
            '--add-drop-table',          // re-importing replaces cleanly
            '--column-statistics=0',     // MySQL 8 client vs older server
        ];
    }

    private function dump(string $binary, array $flags, array $tables, string $file, bool $append): bool
    {
        $db = config('database.connections.mysql.database');

        $process = new Process(
            array_merge([$binary], $flags, [$db], $tables),
            null,
            ['MYSQL_PWD' => (string) config('database.connections.mysql.password')]
        );
        $process->setTimeout(1800);

        // Streamed rather than buffered: a catalog dump can outgrow PHP's
        // memory limit if the whole thing is held as a string first.
        $handle = fopen($file, $append ? 'a' : 'w');

        $process->run(function ($type, $buffer) use ($handle) {
            if ($type === Process::OUT) {
                fwrite($handle, $buffer);
            }
        });

        fclose($handle);

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            // A --column-statistics complaint means an older mysqldump; retry
            // without it rather than making the operator debug flags.
            if (str_contains($error, 'column-statistics')) {
                return $this->dump(
                    $binary,
                    array_values(array_diff($flags, ['--column-statistics=0'])),
                    $tables,
                    $file,
                    $append
                );
            }

            $this->components->error('mysqldump failed: '.mb_substr($error, 0, 300));

            return false;
        }

        return true;
    }

    private function gzip(string $file): string
    {
        $target = $file.'.gz';
        $in = fopen($file, 'rb');
        $out = gzopen($target, 'wb9');

        while (!feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
        @unlink($file);

        return $target;
    }

    private function archiveUploads(string $dir): void
    {
        $source = storage_path('app/public');

        if (!File::isDirectory($source) || File::files($source) === []) {
            $this->components->warn('storage/app/public is empty — most images are legacy CDN URLs, nothing to archive.');

            return;
        }

        $target = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'uploads_'.now()->format('Y-m-d_Hi').'.zip';
        $zip = new \ZipArchive();

        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->components->error('Could not create '.$target);

            return;
        }

        foreach (File::allFiles($source) as $f) {
            $zip->addFile($f->getPathname(), $f->getRelativePathname());
        }

        $zip->close();

        $this->components->twoColumnDetail('Uploads', $this->describe($target));
        $this->line('  <fg=gray>Unzip that into storage/app/public on the server.</>');
    }

    /** Only dump transient tables that actually exist on this machine. */
    private function existingTables(): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            \Illuminate\Support\Facades\DB::select('SHOW TABLES')
        );
    }

    /** Looks in .env first, then the local Apache stack, then PATH. */
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

    private function describe(string $file): string
    {
        return $file.'  ('.number_format(filesize($file) / 1048576, 2).' MB)';
    }
}
