<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('is_diagnosed')->default(false)->after('has_rehab_clearance');
            $table->softDeletes();
        });

        DB::table('patients')->update([
            'is_diagnosed' => DB::raw('has_rehab_clearance'),
        ]);

        Schema::table('staff', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password');
        });

        DB::table('staff')->where('staff_id', 'KB001')->update(['role' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_diagnosed');
        });
    }
};
