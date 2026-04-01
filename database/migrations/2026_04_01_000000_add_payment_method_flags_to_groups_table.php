<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('payment_method_card')->default(true)->after('is_administrator');
            $table->boolean('payment_method_cash')->default(true)->after('payment_method_card');
            $table->boolean('payment_method_eft')->default(true)->after('payment_method_cash');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['payment_method_card', 'payment_method_cash', 'payment_method_eft']);
        });
    }
};
