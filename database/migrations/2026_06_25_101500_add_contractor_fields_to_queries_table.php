<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('description');
            $table->string('company_registration_no')->nullable()->after('company_name');
            $table->string('company_address')->nullable()->after('company_registration_no');
            $table->string('company_email')->nullable()->after('company_address');
            $table->string('company_contact_number')->nullable()->after('company_email');
            $table->string('company_website')->nullable()->after('company_contact_number');
            $table->timestamp('accepted_at')->nullable()->after('responded_at');
            $table->foreignId('accepted_customer_id')->nullable()->after('accepted_at')->constrained('customers')->nullOnDelete();
            $table->foreignId('accepted_contact_id')->nullable()->after('accepted_customer_id')->constrained('contacts')->nullOnDelete();

            $table->index('accepted_customer_id');
            $table->index('accepted_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropIndex(['accepted_customer_id']);
            $table->dropIndex(['accepted_contact_id']);
            $table->dropConstrainedForeignId('accepted_customer_id');
            $table->dropConstrainedForeignId('accepted_contact_id');
            $table->dropColumn([
                'company_name',
                'company_registration_no',
                'company_address',
                'company_email',
                'company_contact_number',
                'company_website',
                'accepted_at',
            ]);
        });
    }
};
