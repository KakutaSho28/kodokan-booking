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
        Schema::table('patients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('birth_date');
        });

        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('mail_type');
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['sent', 'failed']);
            $table->timestamps();

            $table->index(['patient_id', 'mail_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_logs');

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
