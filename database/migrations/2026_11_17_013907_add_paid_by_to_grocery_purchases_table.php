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
        // Who paid the shop: the fund (the default), or a member out of
        // their own pocket, in which case the fund owes them the total.
        Schema::table('grocery_purchases', function (Blueprint $table) {
            $table->string('paid_by', 16)->default('fund')->after('grand_total');
            $table->foreignId('paid_by_member_id')->nullable()->after('paid_by')->index()->constrained('members')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grocery_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_member_id');
            $table->dropColumn('paid_by');
        });
    }
};
