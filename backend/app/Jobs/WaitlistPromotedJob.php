<?php

namespace App\Jobs;

use App\Events\ReservationPromotedNotification;
use App\Models\Waitlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class WaitlistPromotedJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $waitlistId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $waitlist = Waitlist::query()
            ->with(['patient', 'slot.therapist'])
            ->find($this->waitlistId);

        if (! $waitlist) {
            return;
        }

        event(new ReservationPromotedNotification($waitlist));
    }
}
