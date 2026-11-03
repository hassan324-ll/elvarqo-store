<?php

namespace App\Support;

use App\Security\StaffPermissions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class SqliteTransfer
{
    public const EXCLUDED = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'staff_sessions', 'customer_sessions'];

    public function run(string $path, string $connection, bool $execute = false): array
    {
        if (! app()->environment(['local', 'testing', 'staging']) || config('commerce.wise.enabled')) {
            throw new RuntimeException('Use an offline local/testing/staging destination with Wise disabled.');
        }
        if (! is_file($path) || ! realpath($path)) {
            throw new RuntimeException('Source snapshot does not exist.');
        }
        $target = DB::connection($connection);
        if (! in_array($target->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Destination must be MySQL/MariaDB.');
        }
        $source = new PDO('sqlite:'.realpath($path), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only = ON');
        $source->beginTransaction();
        if ($source->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $source->query('PRAGMA foreign_key_check')->fetch()) {
            throw new RuntimeException('Source integrity/foreign-key verification failed.');
        }
        $tables = $source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $tables = array_values(array_diff($tables, self::EXCLUDED));
        $schema = $target->getSchemaBuilder();
        $destinationTables = array_values(array_diff($schema->getTableListing($target->getDatabaseName(), false), self::EXCLUDED));
        sort($destinationTables);
        if ($tables !== $destinationTables) {
            throw new RuntimeException('Source and destination business table sets differ; apply current migrations first.');
        }
        $protected = ['staff_audit_events', 'stock_movements', 'commerce_order_snapshots', 'commerce_order_items', 'commerce_order_addresses', 'commerce_stock_events', 'commerce_webhook_events', 'order_notes', 'fulfilment_items', 'fulfilment_parcels', 'courier_booking_attempts', 'fulfilment_labels', 'courier_tracking_events', 'return_inspections', 'after_sales_stock_events', 'after_sales_events', 'customer_orders'];
        foreach ($protected as $table) {
            foreach (['UPDATE', 'DELETE'] as $event) {
                if (! $target->table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $target->getDatabaseName())->where('EVENT_OBJECT_TABLE', $table)->where('EVENT_MANIPULATION', $event)->where('ACTION_TIMING', 'BEFORE')->where('ACTION_STATEMENT', 'like', '%SIGNAL%')->exists()) {
                    throw new RuntimeException('Required immutable evidence trigger missing for '.$table.'. Confirm hosting privileges and run migrations.');
                }
            }
        }
        $counts = [];
        foreach ($tables as $table) {
            $this->identifier($table);
            $columns = array_column($source->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC), 'name');
            $other = $schema->getColumnListing($table);
            sort($columns);
            sort($other);
            if ($columns !== $other) {
                throw new RuntimeException('Column mismatch in '.$table.'.');
            }
            $engine = $target->table('information_schema.TABLES')->where('TABLE_SCHEMA', $target->getDatabaseName())->where('TABLE_NAME', $table)->value('ENGINE');
            if (strtolower((string) $engine) !== 'innodb') {
                throw new RuntimeException('Every destination business table must use InnoDB.');
            }
            if ($table !== 'staff_roles' && $target->table($table)->exists()) {
                throw new RuntimeException('Destination contains records in '.$table.'; use a new database.');
            }
            $counts[$table] = (int) $source->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
        }
        // A freshly migrated destination contains seeded roles only. Never erase custom roles.
        $allowedRoles = array_keys(StaffPermissions::ROLES);
        if ($target->table('staff_roles')->count() !== count($allowedRoles) || $target->table('staff_roles')->whereNotIn('name', $allowedRoles)->exists()) {
            throw new RuntimeException('Destination roles are not a fresh migration.');
        }
        foreach (['staff_users' => ['two_factor_secret'], 'wise_bank_transfers' => ['instructions', 'receipt_reference'], 'customer_notifications' => ['encrypted_payload'], 'order_notifications' => ['encrypted_payload']] as $table => $columns) {
            foreach ($columns as $column) {
                foreach ($source->query('SELECT "'.$column.'" FROM "'.$table.'" WHERE "'.$column.'" IS NOT NULL') as $row) {
                    try {
                        Crypt::decryptString($row[$column]);
                    } catch (\Throwable) {
                        throw new RuntimeException('APP_KEY cannot decrypt source encrypted data; preserve the source key before importing.');
                    }
                }
            }
        }
        if (! $execute) {
            $source->rollBack();

            return $counts;
        }
        // Private offline transfer only. All FK relationships are checked explicitly before commit.
        // This permits self-referencing categories whose parent has a higher ID.
        $target->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $target->transaction(function () use ($source, $target, $tables) {
                $target->table('staff_roles')->delete();
                foreach ($tables as $table) {
                    foreach ($source->query('SELECT * FROM "'.$table.'"', PDO::FETCH_ASSOC) as $row) {
                        $target->table($table)->insert($row);
                    }
                    $expected = $this->digest($source->query('SELECT * FROM "'.$table.'"', PDO::FETCH_ASSOC));
                    $actual = $this->digest($target->table($table)->cursor()->map(fn ($row) => (array) $row));
                    if (! hash_equals($expected, $actual)) {
                        throw new RuntimeException('Record verification failed in '.$table.'; transfer rolled back.');
                    }
                }
                $keys = $target->table('information_schema.KEY_COLUMN_USAGE')->where('TABLE_SCHEMA', $target->getDatabaseName())->whereNotNull('REFERENCED_TABLE_NAME')->get();
                foreach ($keys as $key) {
                    foreach ([$key->TABLE_NAME, $key->COLUMN_NAME, $key->REFERENCED_TABLE_NAME, $key->REFERENCED_COLUMN_NAME] as $id) {
                        $this->identifier($id);
                    }
                    if ($target->table($key->TABLE_NAME.' as child')->leftJoin($key->REFERENCED_TABLE_NAME.' as parent', 'child.'.$key->COLUMN_NAME, '=', 'parent.'.$key->REFERENCED_COLUMN_NAME)->whereNotNull('child.'.$key->COLUMN_NAME)->whereNull('parent.'.$key->REFERENCED_COLUMN_NAME)->exists()) {
                        throw new RuntimeException('Destination foreign-key validation failed; transfer rolled back.');
                    }
                }
            });
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
            $source->rollBack();
        }

        return $counts;
    }

    private function identifier(string $name): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new RuntimeException('Unsupported schema identifier.');
        }
    }

    private function digest(iterable $rows): string
    {
        $hashes = [];
        foreach ($rows as $row) {
            ksort($row);
            foreach ($row as &$value) {
                if ($value !== null) {
                    $value = (string) $value;
                    // JSON is reserialized by database engines; compare its semantic content.
                    $decoded = json_decode($value);
                    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                        $value = json_encode($this->canonical($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
            }
            unset($value);
            $hashes[] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        }
        sort($hashes);

        return hash('sha256', implode('', $hashes));
    }

    private function canonical(mixed $value): mixed
    {
        if (is_object($value)) {
            $properties = get_object_vars($value);
            ksort($properties);
            foreach ($properties as &$item) {
                $item = $this->canonical($item);
            }

            return (object) $properties;
        }
        if (is_array($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }

        return $value;
    }
}
