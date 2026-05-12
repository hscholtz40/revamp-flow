<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quickbooks_settings', function (Blueprint $table) {
            $table->timestamp('refresh_token_expires_at')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('quickbooks_settings', function (Blueprint $table) {
            $table->dropColumn('refresh_token_expires_at');
        });
    }
};
