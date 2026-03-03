<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('credit_note_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('credit_notes')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove credit note-linked payments before restoring invoice_id to NOT NULL.
        DB::table('payments')->whereNull('invoice_id')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_note_id');
            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
