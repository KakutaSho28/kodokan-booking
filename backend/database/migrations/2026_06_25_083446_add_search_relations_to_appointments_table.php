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
        Schema::create('treatment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('appointment_slot_id')
                ->constrained('staff')
                ->nullOnDelete();
            $table->foreignId('treatment_type_id')
                ->nullable()
                ->after('staff_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('treatment_type_id');
            $table->dropConstrainedForeignId('staff_id');
        });

        Schema::dropIfExists('treatment_types');
    }
};
