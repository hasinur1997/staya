<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many meals of one type a member had on one business date. A
        // count of zero is not stored.
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_type_id')->constrained()->restrictOnDelete();
            $table->date('meal_date')->index();
            $table->unsignedTinyInteger('count');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['member_id', 'meal_type_id', 'meal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
