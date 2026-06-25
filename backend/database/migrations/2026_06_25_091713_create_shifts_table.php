<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::table('therapists', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('id')->constrained('staff')->nullOnDelete();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_day_off')->default(false);
            $table->timestamps();

            $table->unique(['staff_id', 'work_date']);
        });

        DB::table('staff')->update(['is_active' => true]);

        DB::table('therapists')->orderBy('id')->get()->each(function ($therapist, int $index): void {
            $staffId = DB::table('staff')->where('staff_id', sprintf('PT%03d', $index + 1))->value('id');

            if (! $staffId) {
                $staffId = DB::table('staff')->insertGetId([
                    'staff_id' => sprintf('PT%03d', $index + 1),
                    'name' => $therapist->name,
                    'password' => Hash::make('staffpass'),
                    'role' => 'staff',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('therapists')->where('id', $therapist->id)->update(['staff_id' => $staffId]);

            for ($offset = 0; $offset < 60; $offset++) {
                $date = Carbon::today()->addDays($offset);

                if ($date->isSunday()) {
                    continue;
                }

                DB::table('shifts')->updateOrInsert(
                    [
                        'staff_id' => $staffId,
                        'work_date' => $date->toDateString(),
                    ],
                    [
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                        'is_day_off' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');

        Schema::table('therapists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
