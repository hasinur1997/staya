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
        // What the fund still owes a buyer because it could not cover their
        // purchase. One row per purchase; open while reimbursed < amount.
        Schema::create('buyer_payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_purchase_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_reimbursed', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_payables');
    }
};
