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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('card_number')->unique();
            $table->string('name');
            $table->date('birth_date');
            $table->boolean('is_first_visit')->default(false);
            $table->boolean('has_rehab_clearance')->default(false);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_id')->unique();
            $table->string('name');
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('therapists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->timestamps();

            $table->unique(['therapist_id', 'date', 'starts_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_slot_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('booked');
            $table->text('staff_notes')->nullable();
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();

            $table->unique(['patient_id', 'appointment_slot_id']);
        });

        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_tokens');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_slots');
        Schema::dropIfExists('therapists');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('patients');
    }
};
