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
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('reminder_type'); // overdue_invoice, expiring_quote, payment_received, etc.
            $table->string('channel'); // email, sms
            $table->morphs('remindable'); // polymorphic: invoice_id, quote_id, etc.
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('message_sent');
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            
            $table->index(['company_id', 'reminder_type', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
