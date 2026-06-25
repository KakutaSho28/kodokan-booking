@extends('emails.layout', ['title' => 'キャンセル待ち繰り上がりのお知らせ'])

@section('content')
    @php
        $slot = $waitlist->slot;
    @endphp
    <h1 style="margin:0; font-size:20px; color:#111827;">キャンセル待ちから繰り上がりました</h1>
    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">{{ $waitlist->patient?->name }} 様</p>
    <p style="margin:12px 0 0; color:#374151; line-height:1.8;">キャンセル待ち登録中の枠に空きが出たため、繰り上がり対象となりました。予約確定についてはクリニックからの案内をご確認ください。</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px; border:1px solid #e5e7eb; border-radius:8px;">
        <tr>
            <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">対象日</td>
            <td style="padding:12px; font-size:14px; font-weight:700;">{{ $slot?->date?->format('Y年m月d日') }}</td>
        </tr>
        <tr>
            <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">時間</td>
            <td style="padding:12px; font-size:14px; font-weight:700;">{{ substr((string) $slot?->starts_at, 0, 5) }}〜{{ substr((string) $slot?->ends_at, 0, 5) }}</td>
        </tr>
        <tr>
            <td style="width:120px; padding:12px; background:#f9fafb; color:#6b7280; font-size:13px;">担当者</td>
            <td style="padding:12px; font-size:14px; font-weight:700;">{{ $slot?->therapist?->name ?? '未定' }}</td>
        </tr>
    </table>
@endsection
