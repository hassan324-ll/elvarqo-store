<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'commerce_order_snapshots', 'commerce_order_items', 'commerce_order_addresses',
        'commerce_stock_events', 'commerce_webhook_events', 'order_notes', 'fulfilment_items',
        'fulfilment_parcels', 'courier_booking_attempts', 'fulfilment_labels', 'courier_tracking_events',
        'return_inspections', 'after_sales_stock_events', 'after_sales_events', 'customer_orders',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $tables = self::TABLES;
        // Earlier migrations already protect these tables through the mysql driver.
        if (DB::getDriverName() === 'mariadb') {
            $tables = [...$tables, 'staff_audit_events', 'stock_movements'];
        }
        foreach ($tables as $table) {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                $name = $table.'_no_'.strtolower($operation);
                if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::getDatabaseName())->where('TRIGGER_NAME', $name)->exists()) {
                    // Fail closed if the hosting account lacks TRIGGER privileges.
                    DB::unprepared("CREATE TRIGGER `$name` BEFORE $operation ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable ELVARQO evidence'");
                }
            }
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $tables = DB::getDriverName() === 'mariadb' ? [...self::TABLES, 'staff_audit_events', 'stock_movements'] : self::TABLES;
        foreach ($tables as $table) {
            foreach (['update', 'delete'] as $operation) {
                DB::unprepared("DROP TRIGGER IF EXISTS `{$table}_no_{$operation}`");
            }
        }
    }
};
