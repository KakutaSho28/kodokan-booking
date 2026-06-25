<?php

namespace Tests\Feature;

use App\Events\ReservationPromotedNotification;
use App\Jobs\SendPatientMailJob;
use App\Mail\ReservationConfirmedMail;
use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\MailLog;
use App\Models\Patient;
use App\Models\Waitlist;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_create_and_cancel_dispatch_mail_jobs(): void
    {
        Queue::fake();
        $this->seed();

        $patientToken = $this->postJson('/api/auth/patient', [
            'card_number' => '100001',
            'birth_date' => '1984-04-12',
        ])->assertOk()->json('token');

        $slot = AppointmentSlot::query()
            ->whereDoesntHave('appointments', fn ($query) => $query->where('status', Appointment::STATUS_BOOKED))
            ->firstOrFail();

        $appointmentId = $this->withToken($patientToken)
            ->postJson('/api/appointments', ['appointment_slot_id' => $slot->id])
            ->assertCreated()
            ->json('data.id');

        Queue::assertPushed(SendPatientMailJob::class, fn (SendPatientMailJob $job) => $job->mailType === SendPatientMailJob::TYPE_RESERVATION_CONFIRMED
            && $job->modelId === $appointmentId);

        $staffToken = $this->postJson('/api/auth/staff', [
            'staff_id' => 'KB001',
            'password' => 'staffpass',
        ])->assertOk()->json('token');

        $this->withToken($staffToken)
            ->deleteJson('/api/appointments/'.$appointmentId)
            ->assertOk();

        Queue::assertPushed(SendPatientMailJob::class, fn (SendPatientMailJob $job) => $job->mailType === SendPatientMailJob::TYPE_CANCELLATION
            && $job->modelId === $appointmentId);
    }

    public function test_reminder_command_dispatches_tomorrow_reservations(): void
    {
        Queue::fake();
        $this->seed();

        $tomorrowSlot = AppointmentSlot::query()
            ->whereDate('date', CarbonImmutable::tomorrow()->toDateString())
            ->whereDoesntHave('appointments')
            ->firstOrFail();

        $appointment = Appointment::query()->create([
            'patient_id' => Patient::query()->whereNotNull('email')->firstOrFail()->id,
            'appointment_slot_id' => $tomorrowSlot->id,
            'staff_id' => 1,
            'treatment_type_id' => 1,
            'status' => Appointment::STATUS_BOOKED,
        ]);

        $this->artisan('mail:send-reminders')->assertSuccessful();

        Queue::assertPushed(SendPatientMailJob::class, fn (SendPatientMailJob $job) => $job->mailType === SendPatientMailJob::TYPE_REMINDER
            && $job->modelId === $appointment->id);
    }

    public function test_send_patient_mail_job_logs_sent_and_failed_status(): void
    {
        Mail::fake();
        $this->seed();

        $appointment = Appointment::query()->with('patient')->firstOrFail();

        (new SendPatientMailJob(SendPatientMailJob::TYPE_RESERVATION_CONFIRMED, $appointment->id))->handle();

        Mail::assertSent(ReservationConfirmedMail::class);
        $this->assertDatabaseHas('mail_logs', [
            'patient_id' => $appointment->patient_id,
            'mail_type' => SendPatientMailJob::TYPE_RESERVATION_CONFIRMED,
            'status' => MailLog::STATUS_SENT,
        ]);

        $appointment->patient->update(['email' => null]);

        (new SendPatientMailJob(SendPatientMailJob::TYPE_REMINDER, $appointment->id))->handle();

        $this->assertDatabaseHas('mail_logs', [
            'patient_id' => $appointment->patient_id,
            'mail_type' => SendPatientMailJob::TYPE_REMINDER,
            'status' => MailLog::STATUS_FAILED,
        ]);
    }

    public function test_waitlist_promoted_event_dispatches_mail_job(): void
    {
        Queue::fake();
        $this->seed();

        $waitlist = Waitlist::query()->create([
            'patient_id' => Patient::query()->whereNotNull('email')->firstOrFail()->id,
            'slot_id' => AppointmentSlot::query()->firstOrFail()->id,
            'priority' => 1,
            'status' => Waitlist::STATUS_PROMOTED,
        ]);

        event(new ReservationPromotedNotification($waitlist));

        Queue::assertPushed(SendPatientMailJob::class, fn (SendPatientMailJob $job) => $job->mailType === SendPatientMailJob::TYPE_WAITLIST_PROMOTED
            && $job->modelId === $waitlist->id);
    }
}
