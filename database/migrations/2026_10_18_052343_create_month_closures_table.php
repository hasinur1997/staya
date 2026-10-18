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
        // A month is closed while its row has no reopened_at. Closing again
        // after a reopen updates the same row; the history is in activity_log.
        Schema::create('month_closures', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->foreignId('closed_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->foreignId('reopened_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('month_closures');
    }
};
