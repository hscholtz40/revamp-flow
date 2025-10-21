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
        Schema::create('xero_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_name')->nullable();
            
            // Sync settings
            $table->boolean('sync_customers')->default(false);
            $table->boolean('sync_products')->default(false);
            $table->boolean('sync_invoices')->default(false);
            
            // Sync direction settings
            $table->boolean('sync_customers_to_xero')->default(false);
            $table->boolean('sync_customers_from_xero')->default(false);
            $table->boolean('sync_products_to_xero')->default(false);
            $table->boolean('sync_products_from_xero')->default(false);
            $table->boolean('sync_invoices_to_xero')->default(false);
            $table->boolean('sync_invoices_from_xero')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xero_settings');
    }
};
