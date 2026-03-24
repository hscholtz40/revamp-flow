<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')->nullable()->change();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('xero_contact_person_id')->nullable()->after('supplier_id');
            $table->index(['company_id', 'supplier_id']);
            $table->index(['company_id', 'xero_contact_person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'xero_contact_person_id']);
            $table->dropIndex(['company_id', 'supplier_id']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('xero_contact_person_id');

            $table->dropForeign(['customer_id']);
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }
};
