<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobcard_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobcard_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->dateTime('transitioned_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['jobcard_id', 'transitioned_at']);
        });

        // Seed existing jobcards with an initial transition
        $jobcards = DB::table('jobcards')->select('id', 'status', 'created_at')->get();
        foreach ($jobcards as $jobcard) {
            DB::table('jobcard_status_transitions')->insert([
                'jobcard_id' => $jobcard->id,
                'from_status' => null,
                'to_status' => $jobcard->status,
                'transitioned_at' => $jobcard->created_at,
                'user_id' => null,
                'created_at' => $jobcard->created_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobcard_status_transitions');
    }
};
