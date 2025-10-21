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
        Schema::table('group_permissions', function (Blueprint $table) {
            $table->boolean('can_edit_salesperson')->default(false)->after('can_edit_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_permissions', function (Blueprint $table) {
            $table->dropColumn('can_edit_salesperson');
        });
    }
};
