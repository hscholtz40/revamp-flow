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
        Schema::create('pdf_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('module'); // invoice, quote, jobcard, proforma-invoice
            $table->string('name');
            $table->longText('html_template');
            $table->longText('css_styles')->nullable();
            $table->json('default_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('module');
            $table->index('is_active');
            $table->unique(['company_id', 'module', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_templates');
    }
};
