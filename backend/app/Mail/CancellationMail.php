<?php

namespace App\Mail;

use App\Mail\Concerns\BuildsClinicMailHtml;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CancellationMail extends Mailable
{
    use BuildsClinicMailHtml, Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【講道館ビルクリニック】リハビリ予約キャンセルのお知らせ',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->clinicLayout(
                'リハビリ予約キャンセルのお知らせ',
                $this->heading('リハビリ予約をキャンセルしました')
                    .$this->paragraph($this->appointment->patient?->name.' 様')
                    .$this->paragraph('以下の予約のキャンセルが完了しました。')
                    .$this->reservationDetail($this->appointment)
                    .$this->paragraph('改めて予約を希望される場合は、予約画面から空き枠をご確認ください。'),
            ),
        );
    }
}
