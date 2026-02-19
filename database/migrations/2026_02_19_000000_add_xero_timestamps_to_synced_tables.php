<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers',
        'invoices',
        'products',
        'suppliers',
        'quotes',
        'tax_rates',
        'bank_accounts',
        'chart_of_accounts',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('xero_updated_at')->nullable();
                $table->timestamp('xero_created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['xero_updated_at', 'xero_created_at']);
            });
        }
    }
};
