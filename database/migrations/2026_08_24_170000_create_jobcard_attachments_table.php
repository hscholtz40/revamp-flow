<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobcard_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobcard_id')->constrained('jobcards')->cascadeOnDelete();
            $table->string('path');
            $table->string('type')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('jobcard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobcard_attachments');
    }
};
