@php
    $slot = $appointment->slot;
    $therapist = $slot?->therapist;
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px; border:1px solid #e5e7eb; border-radius:8px;">
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">予約日</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">{{ $slot?->date?->format('Y年m月d日') }}</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">予約時間</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">{{ substr((string) $slot?->starts_at, 0, 5) }}〜{{ substr((string) $slot?->ends_at, 0, 5) }}</td>
    </tr>
    <tr>
        <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">担当者</td>
        <td style="padding:12px; font-size:14px; font-weight:700;">{{ $therapist?->name ?? '未定' }}</td>
    </tr>
</table>
