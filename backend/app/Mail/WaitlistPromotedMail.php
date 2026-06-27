<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsClinicMailHtml;
use App\Models\Waitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistPromotedMail extends Mailable
{
    use BuildsClinicMailHtml, Queueable, SerializesModels;

    public function __construct(public Waitlist $waitlist) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【講道館ビルクリニック】キャンセル待ち繰り上がりのお知らせ',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->clinicLayout(
                'キャンセル待ち繰り上がりのお知らせ',
                $this->heading('キャンセル待ちから繰り上がりました')
                    .$this->paragraph($this->waitlist->patient?->name.' 様')
                    .$this->paragraph('キャンセル待ち登録中の枠に空きが出たため、繰り上がり対象となりました。予約確定についてはクリニックからの案内をご確認ください。')
                    .$this->waitlistDetail($this->waitlist),
            ),
        );
    }
}
