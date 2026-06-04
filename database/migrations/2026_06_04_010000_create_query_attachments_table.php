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
        Schema::create('query_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('query_id')->constrained('queries')->onDelete('cascade');
            $table->string('path');
            $table->string('type')->nullable();
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->index('query_id');
        });

        // Migrate existing single attachments into the new table.
        if (Schema::hasColumn('queries', 'attachment_path')) {
            DB::table('queries')
                ->whereNotNull('attachment_path')
                ->orderBy('id')
                ->each(function ($query) {
                    DB::table('query_attachments')->insert([
                        'query_id' => $query->id,
                        'path' => $query->attachment_path,
                        'type' => $query->attachment_type,
                        'original_name' => null,
                        'created_at' => $query->created_at ?? now(),
                        'updated_at' => $query->updated_at ?? now(),
                    ]);
                });

            Schema::table('queries', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_type')->nullable();
        });

        // Restore the first attachment back onto the query row.
        foreach (DB::table('query_attachments')->orderBy('id')->get() as $attachment) {
            DB::table('queries')
                ->where('id', $attachment->query_id)
                ->whereNull('attachment_path')
                ->update([
                    'attachment_path' => $attachment->path,
                    'attachment_type' => $attachment->type,
                ]);
        }

        Schema::dropIfExists('query_attachments');
    }
};
