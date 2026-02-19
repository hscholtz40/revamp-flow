<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('xero_credit_note_id')->nullable()->index();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->string('credit_note_number')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'submitted', 'authorised', 'paid', 'voided'])->default('draft');
            $table->date('credit_note_date');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(15);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('remaining_credit', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('xero_updated_at')->nullable();
            $table->timestamp('xero_created_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('credit_note_date');
        });

        Schema::create('credit_note_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tax_rate_id')->nullable()->constrained()->onDelete('set null');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('account_code')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['credit_note_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_line_items');
        Schema::dropIfExists('credit_notes');
    }
};
