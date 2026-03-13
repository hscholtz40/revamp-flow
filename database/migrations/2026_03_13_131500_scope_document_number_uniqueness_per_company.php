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
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_quote_number_unique');
            $table->unique(['company_id', 'quote_number'], 'quotes_company_id_quote_number_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_invoice_number_unique');
            $table->unique(['company_id', 'invoice_number'], 'invoices_company_id_invoice_number_unique');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropUnique('jobcards_job_number_unique');
            $table->unique(['company_id', 'job_number'], 'jobcards_company_id_job_number_unique');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_number_unique');
            $table->unique(['company_id', 'po_number'], 'purchase_orders_company_id_po_number_unique');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropUnique('credit_notes_credit_note_number_unique');
            $table->unique(['company_id', 'credit_note_number'], 'credit_notes_company_id_credit_note_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropUnique('quotes_company_id_quote_number_unique');
            $table->unique('quote_number', 'quotes_quote_number_unique');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_company_id_invoice_number_unique');
            $table->unique('invoice_number', 'invoices_invoice_number_unique');
        });

        Schema::table('jobcards', function (Blueprint $table) {
            $table->dropUnique('jobcards_company_id_job_number_unique');
            $table->unique('job_number', 'jobcards_job_number_unique');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_company_id_po_number_unique');
            $table->unique('po_number', 'purchase_orders_po_number_unique');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropUnique('credit_notes_company_id_credit_note_number_unique');
            $table->unique('credit_note_number', 'credit_notes_credit_note_number_unique');
        });
    }
};
