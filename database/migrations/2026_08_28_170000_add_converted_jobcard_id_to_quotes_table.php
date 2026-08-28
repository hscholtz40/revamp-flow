<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('converted_jobcard_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('jobcards')
                ->nullOnDelete();
        });

        DB::table('quotes')
            ->whereNull('converted_jobcard_id')
            ->orderBy('id')
            ->chunkById(200, function ($quotes): void {
                foreach ($quotes as $quote) {
                    $jobcardId = DB::table('jobcards')
                        ->where('source_type', 'quote')
                        ->where('source_id', $quote->id)
                        ->orderByDesc('id')
                        ->value('id');

                    if ($jobcardId !== null) {
                        DB::table('quotes')
                            ->where('id', $quote->id)
                            ->update(['converted_jobcard_id' => $jobcardId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_jobcard_id');
        });
    }
};
