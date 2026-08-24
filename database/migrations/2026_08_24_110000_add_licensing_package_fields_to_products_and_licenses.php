<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_licensing_package')->default(false)->after('is_active');
            $table->string('package_code', 50)->nullable()->after('is_licensing_package');
            $table->unsignedInteger('license_standard_users')->default(0)->after('package_code');
            $table->unsignedInteger('license_limited_users')->default(0)->after('license_standard_users');
            $table->unsignedInteger('monthly_credits')->default(0)->after('license_limited_users');
        });

        Schema::table('queries', function (Blueprint $table) {
            $table->foreignId('selected_product_id')->nullable()->after('selected_package')->constrained('products')->nullOnDelete();
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('source_query_id')->nullable()->after('product_id')->constrained('queries')->nullOnDelete();
            $table->unsignedInteger('monthly_credits')->default(0)->after('standard_users');
        });

        Schema::table('instance_licenses', function (Blueprint $table) {
            $table->unsignedInteger('monthly_credits')->nullable()->after('standard_users');
            $table->string('customer_name')->nullable()->after('monthly_credits');
        });
    }

    public function down(): void
    {
        Schema::table('instance_licenses', function (Blueprint $table) {
            $table->dropColumn(['monthly_credits', 'customer_name']);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_query_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('monthly_credits');
        });

        Schema::table('queries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_product_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_licensing_package',
                'package_code',
                'license_standard_users',
                'license_limited_users',
                'monthly_credits',
            ]);
        });
    }
};
