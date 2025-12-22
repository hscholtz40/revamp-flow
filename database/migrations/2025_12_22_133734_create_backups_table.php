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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('type')->default('manual'); // manual, scheduled, automatic
            $table->string('storage_type')->default('local'); // local, s3, google_drive, dropbox
            $table->string('file_path');
            $table->string('file_name');
            $table->bigInteger('file_size'); // in bytes
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional info like tables backed up, etc.
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('frequency'); // daily, weekly, monthly
            $table->time('time'); // Time of day to run
            $table->string('day_of_week')->nullable(); // For weekly (monday, tuesday, etc.)
            $table->integer('day_of_month')->nullable(); // For monthly (1-31)
            $table->string('storage_type')->default('local'); // local, s3, google_drive, dropbox
            $table->boolean('is_active')->default(true);
            $table->integer('retention_days')->default(30); // How long to keep backups
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
        Schema::dropIfExists('backups');
    }
};
