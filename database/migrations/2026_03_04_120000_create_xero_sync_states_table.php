<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xero_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module', 100);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'module']);
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xero_sync_states');
    }
};
