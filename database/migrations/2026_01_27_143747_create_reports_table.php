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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('report_template_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->enum('entity_type', ['invoice', 'quote', 'jobcard'])->default('invoice');
            $table->json('config'); // Columns, grouping, sorting, etc.
            $table->json('filters')->nullable(); // Date ranges, status filters, etc.
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['company_id', 'entity_type']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
