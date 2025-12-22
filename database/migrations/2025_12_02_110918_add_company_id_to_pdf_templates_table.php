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
        // Check if the column already exists before adding it
        // This handles cases where the table was created with company_id already included
        // (e.g., in create_pdf_templates_table migration)
        if (!Schema::hasColumn('pdf_templates', 'company_id')) {
            Schema::table('pdf_templates', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                $table->index('company_id');
            });
        }
        // If column already exists, assume foreign key and index are also already set up
        // from the create migration, so we don't need to do anything
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if column exists (might have been created in create migration)
        if (Schema::hasColumn('pdf_templates', 'company_id')) {
            Schema::table('pdf_templates', function (Blueprint $table) {
                // Try to drop foreign key and index, but don't fail if they don't exist
                try {
                    $table->dropForeign(['company_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
                try {
                    $table->dropIndex(['company_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                $table->dropColumn('company_id');
            });
        }
    }
};
