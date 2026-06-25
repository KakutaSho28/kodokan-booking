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
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('appointment_slots')->cascadeOnDelete();
            $table->unsignedInteger('priority');
            $table->enum('status', ['waiting', 'promoted', 'expired'])->default('waiting');
            $table->timestamps();

            $table->unique(['patient_id', 'slot_id']);
            $table->index(['slot_id', 'status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
