<?php

namespace App\Console\Commands;

use App\Jobs\SendPatientMailJob;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendReservationRemindersCommand extends Command
{
    protected $signature = 'mail:send-reminders';

    protected $description = '翌日のリハビリ予約リマインドメールをキューへ登録します。';

    public function handle(): int
    {
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();
        $appointments = Appointment::query()
            ->where('status', Appointment::STATUS_BOOKED)
            ->whereHas('slot', fn ($query) => $query->whereDate('date', $tomorrow))
            ->pluck('id');

        $appointments->each(fn (int $appointmentId) => SendPatientMailJob::dispatch(
            SendPatientMailJob::TYPE_REMINDER,
            $appointmentId,
        ));

        $this->info($appointments->count().'件のリマインドメールをキューへ登録しました。');

        return self::SUCCESS;
    }
}
