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
        // A single row (id 1): the cached fund balance and the row every
        // posting locks. It must always equal the ledger sum.
        Schema::create('fund', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 12, 2)->default('0.00');
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('fund')->insert(['id' => 1, 'balance' => '0.00', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund');
    }
};
