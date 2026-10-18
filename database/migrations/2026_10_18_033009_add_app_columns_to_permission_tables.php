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
        Schema::table('permissions', function (Blueprint $table) {
            // Catalog resource (e.g. "deposit"), used to group the role editor.
            $table->string('group', 50)->default('')->after('guard_name')->index();
            $table->string('description')->nullable()->after('group');
        });

        Schema::table('roles', function (Blueprint $table) {
            // The seeded Admin, Manager and Member roles: never renamed or deleted.
            $table->boolean('is_system')->default(false)->after('guard_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn(['group', 'description']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
