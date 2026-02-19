<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->boolean('is_default_sales')->default(false)->after('is_active');
            $table->boolean('is_default_purchasing')->default(false)->after('is_default_sales');
        });

        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('tax_amount')->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('quote_line_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('tax_amount')->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('jobcard_line_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('tax_amount')->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('credit_note_line_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('account_code')->constrained('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('tax_amount')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn(['is_default_sales', 'is_default_purchasing']);
        });

        $tables = ['invoice_line_items', 'quote_line_items', 'jobcard_line_items', 'credit_note_line_items', 'purchase_order_items'];
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
