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
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('twilio'); // twilio, meta, etc.
            $table->text('api_key')->nullable(); // API key/token (can be long for Meta)
            $table->text('api_secret')->nullable(); // API secret/password
            $table->string('account_sid')->nullable(); // For Twilio
            $table->string('from_number')->nullable(); // WhatsApp Business number
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
