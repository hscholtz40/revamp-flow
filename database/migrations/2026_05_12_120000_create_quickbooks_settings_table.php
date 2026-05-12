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
        Schema::create('quickbooks_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('realm_id')->nullable();
            $table->string('realm_name')->nullable();

            $table->boolean('sync_customers')->default(false);
            $table->boolean('sync_products')->default(false);
            $table->boolean('sync_invoices')->default(false);

            $table->boolean('sync_customers_to_quickbooks')->default(false);
            $table->boolean('sync_customers_from_quickbooks')->default(false);
            $table->boolean('sync_products_to_quickbooks')->default(false);
            $table->boolean('sync_products_from_quickbooks')->default(false);
            $table->boolean('sync_invoices_to_quickbooks')->default(false);
            $table->boolean('sync_invoices_from_quickbooks')->default(false);
            $table->boolean('sync_suppliers_to_quickbooks')->default(false);
            $table->boolean('sync_suppliers_from_quickbooks')->default(false);
            $table->boolean('sync_quotes_to_quickbooks')->default(false);
            $table->boolean('sync_quotes_from_quickbooks')->default(false);
            $table->boolean('sync_tax_rates_from_quickbooks')->default(false);
            $table->boolean('sync_bank_accounts_from_quickbooks')->default(false);
            $table->boolean('sync_chart_of_accounts_from_quickbooks')->default(false);
            $table->boolean('sync_credit_notes_to_quickbooks')->default(false);
            $table->boolean('sync_credit_notes_from_quickbooks')->default(false);
            $table->boolean('sync_purchase_orders_to_quickbooks')->default(false);
            $table->boolean('sync_purchase_orders_from_quickbooks')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_settings');
    }
};
