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
        // Only an approved deposit touches the fund, through its ledger entry.
        // Amount, date, member and method never change after insert.
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('deposit_date')->index();
            $table->string('method', 16)->default('cash');
            $table->string('status', 16)->default('pending');
            $table->foreignId('submitted_by')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_self_approved')->default(false)->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['status', 'deposit_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
