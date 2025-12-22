<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't support ALTER TABLE CHANGE, so we need to recreate the table
            $this->renameColumnsForSqlite([
                'invoice_sent_email_enabled' => 'invoice_created_email_enabled',
                'invoice_sent_sms_enabled' => 'invoice_created_sms_enabled',
                'invoice_sent_email_template' => 'invoice_created_email_template',
                'invoice_sent_sms_template' => 'invoice_created_sms_template',
                'quote_sent_email_enabled' => 'quote_created_email_enabled',
                'quote_sent_sms_enabled' => 'quote_created_sms_enabled',
                'quote_sent_email_template' => 'quote_created_email_template',
                'quote_sent_sms_template' => 'quote_created_sms_template',
            ]);
        } else {
            // MySQL/MariaDB syntax
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_email_enabled` `invoice_created_email_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_sms_enabled` `invoice_created_sms_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_email_template` `invoice_created_email_template` TEXT NULL');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_sent_sms_template` `invoice_created_sms_template` TEXT NULL');
            
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_email_enabled` `quote_created_email_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_sms_enabled` `quote_created_sms_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_email_template` `quote_created_email_template` TEXT NULL');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_sent_sms_template` `quote_created_sms_template` TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // Reverse the column renames for SQLite
            $this->renameColumnsForSqlite([
                'invoice_created_email_enabled' => 'invoice_sent_email_enabled',
                'invoice_created_sms_enabled' => 'invoice_sent_sms_enabled',
                'invoice_created_email_template' => 'invoice_sent_email_template',
                'invoice_created_sms_template' => 'invoice_sent_sms_template',
                'quote_created_email_enabled' => 'quote_sent_email_enabled',
                'quote_created_sms_enabled' => 'quote_sent_sms_enabled',
                'quote_created_email_template' => 'quote_sent_email_template',
                'quote_created_sms_template' => 'quote_sent_sms_template',
            ]);
        } else {
            // MySQL/MariaDB syntax
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_email_enabled` `invoice_sent_email_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_sms_enabled` `invoice_sent_sms_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_email_template` `invoice_sent_email_template` TEXT NULL');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `invoice_created_sms_template` `invoice_sent_sms_template` TEXT NULL');
            
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_email_enabled` `quote_sent_email_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_sms_enabled` `quote_sent_sms_enabled` BOOLEAN DEFAULT FALSE');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_email_template` `quote_sent_email_template` TEXT NULL');
            DB::statement('ALTER TABLE `reminder_settings` CHANGE `quote_created_sms_template` `quote_sent_sms_template` TEXT NULL');
        }
    }

    /**
     * Rename columns for SQLite by recreating the table
     */
    private function renameColumnsForSqlite(array $columnMap): void
    {
        // Check if table exists and has the old columns
        if (!Schema::hasTable('reminder_settings')) {
            return; // Table doesn't exist, nothing to rename
        }
        
        // Check if any of the old columns exist
        $hasOldColumns = false;
        foreach (array_keys($columnMap) as $oldColumn) {
            if (Schema::hasColumn('reminder_settings', $oldColumn)) {
                $hasOldColumns = true;
                break;
            }
        }
        
        if (!$hasOldColumns) {
            return; // Columns already renamed or don't exist
        }
        
        // Get current table structure
        $columns = DB::select("PRAGMA table_info(reminder_settings)");
        
        // Build new column list with renamed columns
        $newColumns = [];
        foreach ($columns as $column) {
            $columnName = $column->name;
            $newColumnName = $columnMap[$columnName] ?? $columnName;
            
            $type = strtoupper($column->type);
            $notNull = $column->notnull ? 'NOT NULL' : '';
            $default = '';
            if ($column->dflt_value !== null) {
                // SQLite PRAGMA returns default values as strings
                $dfltVal = (string)$column->dflt_value;
                $len = strlen($dfltVal);
                
                // Remove surrounding quotes if present
                if ($len >= 2 && $dfltVal[0] === "'" && $dfltVal[$len - 1] === "'") {
                    $dfltVal = substr($dfltVal, 1, -1);
                } elseif ($len >= 2 && $dfltVal[0] === '"' && $dfltVal[$len - 1] === '"') {
                    $dfltVal = substr($dfltVal, 1, -1);
                }
                
                // Check if it's numeric (after removing quotes)
                if (is_numeric($dfltVal)) {
                    $default = "DEFAULT {$dfltVal}";
                } else {
                    // String default - escape single quotes for SQL
                    $dfltVal = str_replace("'", "''", $dfltVal);
                    $default = "DEFAULT '{$dfltVal}'";
                }
            }
            $pk = $column->pk ? 'PRIMARY KEY' : '';
            
            $parts = array_filter([$type, $notNull, $default, $pk]);
            $def = implode(' ', $parts);
            $newColumns[] = "`{$newColumnName}` {$def}";
        }
        
        // Create new table with renamed columns
        DB::statement("CREATE TABLE `reminder_settings_new` (" . implode(', ', $newColumns) . ")");
        
        // Copy data with column mapping
        $selectColumns = [];
        $insertColumns = [];
        foreach ($columns as $column) {
            $columnName = $column->name;
            $newColumnName = $columnMap[$columnName] ?? $columnName;
            $selectColumns[] = "`{$columnName}`";
            $insertColumns[] = "`{$newColumnName}`";
        }
        
        DB::statement("INSERT INTO `reminder_settings_new` (" . implode(', ', $insertColumns) . ") SELECT " . implode(', ', $selectColumns) . " FROM `reminder_settings`");
        
        // Drop old table and rename new one
        DB::statement("DROP TABLE `reminder_settings`");
        DB::statement("ALTER TABLE `reminder_settings_new` RENAME TO `reminder_settings`");
    }
};
