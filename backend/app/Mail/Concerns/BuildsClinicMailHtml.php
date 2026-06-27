<?php

namespace App\Mail\Concerns;

use App\Models\Appointment;
use App\Models\Waitlist;

trait BuildsClinicMailHtml
{
    protected function clinicLayout(string $title, string $body): string
    {
        return '<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>'.$this->e($title).'</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; color:#111827; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px; background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background:#2563eb; color:#ffffff; padding:20px 24px;">
                            <div style="font-size:18px; font-weight:700;">講道館ビルクリニック</div>
                            <div style="font-size:13px; margin-top:4px;">リハビリ予約システム</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">'.$body.'</td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e5e7eb; padding:16px 24px; color:#6b7280; font-size:12px; line-height:1.7;">
                            講道館ビルクリニック｜〒112-0003 東京都文京区春日1-16-30｜TEL: 03-5842-6311
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    protected function reservationDetail(Appointment $appointment): string
    {
        $slot = $appointment->slot;
        $therapist = $slot?->therapist;

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px; border:1px solid #e5e7eb; border-radius:8px;">
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">予約日</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e($slot?->date?->format('Y年m月d日') ?? '').'</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">予約時間</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e(substr((string) $slot?->starts_at, 0, 5)).'〜'.$this->e(substr((string) $slot?->ends_at, 0, 5)).'</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">担当者</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e($therapist?->name ?? '未定').'</td>
    </tr>
</table>';
    }

    protected function waitlistDetail(Waitlist $waitlist): string
    {
        $slot = $waitlist->slot;

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px; border:1px solid #e5e7eb; border-radius:8px;">
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">対象日</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e($slot?->date?->format('Y年m月d日') ?? '').'</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">時間</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e(substr((string) $slot?->starts_at, 0, 5)).'〜'.$this->e(substr((string) $slot?->ends_at, 0, 5)).'</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">担当者</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">'.$this->e($slot?->therapist?->name ?? '未定').'</td>
    </tr>
</table>';
    }

    protected function paragraph(string $text): string
    {
        return '<p style="margin:12px 0 0; color:#374151; line-height:1.8;">'.$this->e($text).'</p>';
    }

    protected function heading(string $text): string
    {
        return '<h1 style="margin:0; font-size:20px; color:#111827;">'.$this->e($text).'</h1>';
    }

    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
