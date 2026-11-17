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
        // A purchase is never hard-deleted: a void sets `status = voided` and
        // reverses its ledger effect. The owner's edit window counts from the
        // server's `created_at`.
        Schema::create('grocery_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('purchase_date')->index();
            $table->foreignId('buyer_member_id')->constrained('members')->restrictOnDelete();
            $table->decimal('grand_total', 12, 2);
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'purchase_date']);
            $table->index(['buyer_member_id', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_purchases');
    }
};
