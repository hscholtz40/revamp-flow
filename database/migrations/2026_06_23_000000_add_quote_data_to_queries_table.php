<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->json('quote_line_items')->nullable()->after('description');
            $table->decimal('quote_total_amount', 12, 2)->nullable()->after('quote_line_items');
            $table->string('quote_client_email', 255)->nullable()->after('quote_total_amount');
            $table->string('quote_client_phone', 50)->nullable()->after('quote_client_email');
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropColumn([
                'quote_line_items',
                'quote_total_amount',
                'quote_client_email',
                'quote_client_phone',
            ]);
        });
    }
};
