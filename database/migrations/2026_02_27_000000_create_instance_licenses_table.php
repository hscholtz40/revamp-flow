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
        Schema::create('instance_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_key', 64)->nullable();
            $table->string('status', 32)->default('unvalidated');
            $table->string('message')->nullable();
            $table->string('licensed_url')->nullable();
            $table->unsignedInteger('limited_users')->nullable();
            $table->unsignedInteger('standard_users')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instance_licenses');
    }
};
