<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->after('name');
            $table->string('company_cell')->nullable()->after('phone');
            $table->string('company_tel')->nullable()->after('company_cell');
            $table->string('contact_first_name')->nullable()->after('company_tel');
            $table->string('contact_last_name')->nullable()->after('contact_first_name');
            $table->string('contact_cell')->nullable()->after('contact_last_name');
            $table->string('contact_email')->nullable()->after('contact_cell');
        });

        DB::table('customers')
            ->whereNull('company_tel')
            ->whereNotNull('phone')
            ->update(['company_tel' => DB::raw('phone')]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'registration_number',
                'company_cell',
                'company_tel',
                'contact_first_name',
                'contact_last_name',
                'contact_cell',
                'contact_email',
            ]);
        });
    }
};
