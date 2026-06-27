<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsClinicMailHtml;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmedMail extends Mailable
{
    use BuildsClinicMailHtml, Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【講道館ビルクリニック】リハビリ予約確定のお知らせ',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->clinicLayout(
                'リハビリ予約確定のお知らせ',
                $this->heading('リハビリ予約を確定しました')
                    .$this->paragraph($this->appointment->patient?->name.' 様')
                    .$this->paragraph('以下の内容でリハビリ予約を受け付けました。')
                    .$this->reservationDetail($this->appointment)
                    .$this->paragraph('当日は診察券をお持ちのうえ、予約時間までに受付へお越しください。'),
            ),
        );
    }
}
