<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsClinicMailHtml;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use BuildsClinicMailHtml, Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【講道館ビルクリニック】明日のリハビリ予約リマインダー',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->clinicLayout(
                '明日のリハビリ予約リマインダー',
                $this->heading('明日のリハビリ予約のお知らせ')
                    .$this->paragraph($this->appointment->patient?->name.' 様')
                    .$this->paragraph('明日のリハビリ予約についてお知らせします。')
                    .$this->reservationDetail($this->appointment)
                    .$this->paragraph('変更やキャンセルが必要な場合は、クリニックまでご連絡ください。'),
            ),
        );
    }
}
