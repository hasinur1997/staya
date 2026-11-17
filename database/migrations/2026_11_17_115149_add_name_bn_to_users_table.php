<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. `name` stays the English name; existing accounts
     * start with it in the Bangla column (members get their Bangla name).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_bn')->default('')->after('name');
        });

        DB::table('users')->update(['name_bn' => DB::raw('name')]);

        foreach (DB::table('members')->get(['user_id', 'name_bn']) as $member) {
            DB::table('users')->where('id', $member->user_id)->update(['name_bn' => $member->name_bn]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name_bn');
        });
    }
};
