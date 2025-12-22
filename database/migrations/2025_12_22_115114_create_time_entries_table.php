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
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('jobcard_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes')->default(0); // Duration in minutes
            $table->decimal('hourly_rate', 10, 2)->nullable(); // Hourly rate for billing
            $table->boolean('is_billable')->default(true);
            $table->text('description')->nullable();
            $table->enum('status', ['running', 'completed', 'paused'])->default('completed');
            $table->timestamp('started_at')->nullable(); // For timer tracking
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['jobcard_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
