<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            // A query is either a public "enquiry" or a contractor "job" dispatched
            // from an external system (e.g. the Revamp quotes module).
            $table->string('kind')->default('enquiry')->after('company_id');
            $table->string('external_source')->nullable()->after('kind');
            $table->string('external_quote_id')->nullable()->after('external_source');
            $table->string('contractor_company_key')->nullable()->after('external_quote_id');
            // Job lifecycle: contractors accept/decline; first accept wins.
            $table->string('response')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('response');
            $table->string('job_location')->nullable()->after('responded_at');
            $table->decimal('job_latitude', 10, 7)->nullable()->after('job_location');
            $table->decimal('job_longitude', 10, 7)->nullable()->after('job_latitude');

            $table->index(['kind', 'external_quote_id']);
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropIndex(['kind', 'external_quote_id']);
            $table->dropColumn([
                'kind', 'external_source', 'external_quote_id', 'contractor_company_key',
                'response', 'responded_at', 'job_location', 'job_latitude', 'job_longitude',
            ]);
        });
    }
};
