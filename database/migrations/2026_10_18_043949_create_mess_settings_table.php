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
        // A single row (id 1) of mess-wide settings.
        Schema::create('mess_settings', function (Blueprint $table) {
            $table->id();
            $table->string('mess_name', 150)->nullable();
            $table->text('mess_address')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->decimal('opening_balance', 12, 2)->default('0.00');
            $table->date('fund_start_date')->nullable();
            $table->unsignedSmallInteger('grocery_edit_window_minutes')->default(30);
            $table->boolean('members_can_view_balance')->default(true);
            $table->unsignedInteger('max_upload_kb')->default(5120);
            $table->foreignId('updated_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mess_settings');
    }
};
