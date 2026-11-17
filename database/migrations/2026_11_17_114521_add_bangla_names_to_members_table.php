<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Existing members start with their English names in
     * the Bangla columns until someone corrects them.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('name_bn', 150)->default('')->after('last_name');
            $table->string('father_name_bn', 150)->default('')->after('father_name');
            $table->string('mother_name_bn', 150)->default('')->after('mother_name');
        });

        foreach (DB::table('members')->get(['id', 'first_name', 'last_name', 'father_name', 'mother_name']) as $member) {
            DB::table('members')->where('id', $member->id)->update([
                'name_bn' => trim($member->first_name.' '.$member->last_name),
                'father_name_bn' => $member->father_name,
                'mother_name_bn' => $member->mother_name,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['name_bn', 'father_name_bn', 'mother_name_bn']);
        });
    }
};
