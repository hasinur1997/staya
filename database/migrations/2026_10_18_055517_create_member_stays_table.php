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
        // One open stay per member, no overlaps and left_on >= joined_on are
        // enforced by the member actions; they are not portable constraints.
        Schema::create('member_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'joined_on']);
            $table->index(['joined_on', 'left_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_stays');
    }
};
