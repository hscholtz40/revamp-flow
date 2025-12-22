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
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            
            // Versioned model
            $table->string('versionable_type'); // Model class name
            $table->unsignedBigInteger('versionable_id'); // Model ID
            $table->integer('version_number'); // Sequential version number
            
            // Version data
            $table->json('data'); // Complete snapshot of model data
            $table->json('changes')->nullable(); // What changed from previous version
            
            // Version metadata
            $table->string('reason')->nullable(); // Why this version was created
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(false); // Is this the current version
            
            $table->timestamps();
            
            // Indexes
            $table->index(['versionable_type', 'versionable_id', 'version_number'], 'mv_versionable_idx');
            $table->index(['versionable_type', 'versionable_id', 'is_current'], 'mv_current_idx');
            $table->unique(['versionable_type', 'versionable_id', 'version_number'], 'mv_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
