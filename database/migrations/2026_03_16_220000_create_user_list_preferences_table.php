<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_list_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('page_key');
            $table->json('columns');
            $table->timestamps();

            $table->unique(['user_id', 'company_id', 'page_key'], 'user_company_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_list_preferences');
    }
};
