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
        // Append-only: rows are never updated or deleted, so there is no updated_at.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('type', 32);
            $table->string('direction', 6);
            $table->decimal('amount', 12, 2);
            $table->nullableMorphs('source');
            $table->foreignId('reverses_entry_id')->nullable()->unique()->constrained('ledger_entries')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'entry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
