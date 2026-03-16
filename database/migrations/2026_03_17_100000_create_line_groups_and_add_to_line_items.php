<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('line_groups', function (Blueprint $table) {
            $table->id();
            $table->string('line_groupable_type');
            $table->unsignedBigInteger('line_groupable_id');
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['line_groupable_type', 'line_groupable_id']);
        });

        $lineItemTables = [
            'quote_line_items' => 'quote_id',
            'jobcard_line_items' => 'jobcard_id',
            'invoice_line_items' => 'invoice_id',
            'credit_note_line_items' => 'credit_note_id',
            'purchase_order_items' => 'purchase_order_id',
        ];

        foreach ($lineItemTables as $tableName => $documentIdColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($documentIdColumn) {
                $table->foreignId('line_group_id')->nullable()->after($documentIdColumn)->constrained('line_groups')->onDelete('set null');
            });
        }

        // Create default groups for existing documents and assign line items
        $this->migrateExistingDocuments();
    }

    protected function migrateExistingDocuments(): void
    {
        $configs = [
            ['type' => 'App\\Models\\Quote', 'column' => 'quote_id', 'items_table' => 'quote_line_items'],
            ['type' => 'App\\Models\\Jobcard', 'column' => 'jobcard_id', 'items_table' => 'jobcard_line_items'],
            ['type' => 'App\\Models\\Invoice', 'column' => 'invoice_id', 'items_table' => 'invoice_line_items'],
            ['type' => 'App\\Models\\CreditNote', 'column' => 'credit_note_id', 'items_table' => 'credit_note_line_items'],
            ['type' => 'App\\Models\\PurchaseOrder', 'column' => 'purchase_order_id', 'items_table' => 'purchase_order_items'],
        ];

        foreach ($configs as $config) {
            $documentIds = DB::table($config['items_table'])->distinct()->pluck($config['column']);
            foreach ($documentIds as $docId) {
                $groupId = DB::table('line_groups')->insertGetId([
                    'line_groupable_type' => $config['type'],
                    'line_groupable_id' => $docId,
                    'name' => 'Items',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table($config['items_table'])->where($config['column'], $docId)->update(['line_group_id' => $groupId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $lineItemTableNames = ['quote_line_items', 'jobcard_line_items', 'invoice_line_items', 'credit_note_line_items', 'purchase_order_items'];

        foreach ($lineItemTableNames as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('line_group_id');
            });
        }

        Schema::dropIfExists('line_groups');
    }
};
