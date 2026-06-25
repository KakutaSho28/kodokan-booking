<?php

namespace App\Listeners;

use App\Events\ReservationPromotedNotification;
use App\Jobs\SendPatientMailJob;

class SendWaitlistPromotedMail
{
    public function __construct() {}

    public function handle(ReservationPromotedNotification $event): void
    {
        SendPatientMailJob::dispatch(
            SendPatientMailJob::TYPE_WAITLIST_PROMOTED,
            $event->waitlist->id,
        );
    }
}
