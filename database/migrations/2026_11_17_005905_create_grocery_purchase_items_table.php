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
        // Line totals are computed on the server. Items are replaced when a
        // purchase is edited; the activity log keeps the old list.
        Schema::create('grocery_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_purchase_id')->constrained()->cascadeOnDelete();
            $table->string('item_name', 100)->index();
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 10);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['grocery_purchase_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grocery_purchase_items');
    }
};
