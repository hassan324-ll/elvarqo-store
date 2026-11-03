<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SQLite3;

class SnapshotSqlite extends Command
{
    protected $signature = 'database:snapshot-sqlite {destination : New absolute private backup filename}';

    protected $description = 'Create a consistent SQLite online backup without overwriting any existing file';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite' || ! class_exists(SQLite3::class)) {
            $this->error('Requires the SQLite connection and PHP sqlite3 extension.');

            return self::FAILURE;
        }
        $path = $this->argument('destination');
        $parent = realpath(dirname($path));
        $public = realpath(public_path());
        if (! $parent || ! preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) || file_exists($path) || ($public && str_starts_with(strtolower($parent.DIRECTORY_SEPARATOR), strtolower($public.DIRECTORY_SEPARATOR)))) {
            $this->error('Use a new absolute filename in an existing private directory outside public/.');

            return self::FAILURE;
        }
        $handle = @fopen($path, 'x');
        if (! $handle) {
            $this->error('Could not exclusively create the backup file.');

            return self::FAILURE;
        }
        fclose($handle);
        chmod($path, 0600);
        try {
            $source = new SQLite3(DB::connection()->getDatabaseName(), SQLITE3_OPEN_READONLY);
            $destination = new SQLite3($path);
            if (! $source->backup($destination) || $destination->querySingle('PRAGMA integrity_check') !== 'ok') {
                throw new \RuntimeException('Backup verification failed.');
            }
            $destination->close();
            $source->close();
            $this->info('Consistent private snapshot saved. Preserve APP_KEY separately; do not commit or publish either file.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Snapshot failed; remove the incomplete new file before retrying. Original database unchanged.');

            return self::FAILURE;
        }
    }
}
