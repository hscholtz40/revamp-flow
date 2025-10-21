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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bulksms_username',
                'bulksms_password',
                'bulksms_sender_name'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bulksms_username')->nullable()->after('smtp_from_name');
            $table->string('bulksms_password')->nullable()->after('bulksms_username');
            $table->string('bulksms_sender_name')->nullable()->after('bulksms_password');
        });
    }
};