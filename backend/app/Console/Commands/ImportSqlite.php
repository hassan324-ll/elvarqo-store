<?php

namespace App\Console\Commands;

use App\Support\SqliteTransfer;
use Illuminate\Console\Command;
use Throwable;

class ImportSqlite extends Command
{
    protected $signature = 'database:import-sqlite {source : Absolute path to a consistent private SQLite backup} {--connection=mysql} {--execute : Copy into an empty, migrated, offline destination}';

    protected $description = 'Validate or import a SQLite snapshot into MySQL/MariaDB without changing the source';

    public function handle(): int
    {
        try {
            $result = app(SqliteTransfer::class)->run($this->argument('source'), $this->option('connection'), (bool) $this->option('execute'));
            $this->table(['Table', 'Rows'], array_map(fn ($name, $count) => [$name, $count], array_keys($result), array_values($result)));
            $this->info($this->option('execute') ? 'Copy committed; all imported row hashes and foreign keys verified. Copy private media separately. Source unchanged.' : 'Preflight passed; no data written. Stop destination traffic/workers before using --execute.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Database exceptions may include account names or encrypted payloads.
            $this->error($e instanceof \RuntimeException && ! $e instanceof \PDOException ? $e->getMessage() : 'Transfer failed; transaction rolled back. Check schema, privileges and private server diagnostics.');

            return self::FAILURE;
        }
    }
}
