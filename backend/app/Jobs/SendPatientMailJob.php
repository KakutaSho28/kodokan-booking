<?php

namespace App\Jobs;

use App\Mail\CancellationMail;
use App\Mail\ReminderMail;
use App\Mail\ReservationConfirmedMail;
use App\Mail\WaitlistPromotedMail;
use App\Models\Appointment;
use App\Models\MailLog;
use App\Models\Patient;
use App\Models\Waitlist;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPatientMailJob implements ShouldQueue
{
    use Queueable;

    public const TYPE_RESERVATION_CONFIRMED = 'reservation_confirmed';

    public const TYPE_REMINDER = 'reminder';

    public const TYPE_CANCELLATION = 'cancellation';

    public const TYPE_WAITLIST_PROMOTED = 'waitlist_promoted';

    public function __construct(
        public string $mailType,
        public int $modelId,
    ) {}

    public function handle(): void
    {
        [$patient, $mailable] = $this->resolveMail();

        if (! $patient?->email || ! $mailable) {
            $this->log($patient?->id, MailLog::STATUS_FAILED);

            return;
        }

        try {
            Mail::to($patient->email)->send($mailable);
            $this->log($patient->id, MailLog::STATUS_SENT);
        } catch (Throwable) {
            $this->log($patient->id, MailLog::STATUS_FAILED);
        }
    }

    /**
     * @return array{0: Patient|null, 1: Mailable|null}
     */
    private function resolveMail(): array
    {
        if ($this->mailType === self::TYPE_WAITLIST_PROMOTED) {
            $waitlist = Waitlist::query()
                ->with(['patient', 'slot.therapist'])
                ->find($this->modelId);

            return [$waitlist?->patient, $waitlist ? new WaitlistPromotedMail($waitlist) : null];
        }

        $appointment = Appointment::query()
            ->with(['patient', 'slot.therapist'])
            ->find($this->modelId);

        return match ($this->mailType) {
            self::TYPE_RESERVATION_CONFIRMED => [$appointment?->patient, $appointment ? new ReservationConfirmedMail($appointment) : null],
            self::TYPE_REMINDER => [$appointment?->patient, $appointment ? new ReminderMail($appointment) : null],
            self::TYPE_CANCELLATION => [$appointment?->patient, $appointment ? new CancellationMail($appointment) : null],
            default => [null, null],
        };
    }

    private function log(?int $patientId, string $status): void
    {
        if (! $patientId) {
            return;
        }

        MailLog::query()->create([
            'patient_id' => $patientId,
            'mail_type' => $this->mailType,
            'sent_at' => $status === MailLog::STATUS_SENT ? now() : null,
            'status' => $status,
        ]);
    }
}
