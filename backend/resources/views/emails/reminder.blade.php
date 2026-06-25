@extends('emails.layout', ['title' => '明日のリハビリ予約リマインダー'])

@section('content')
    <h1 style="margin:0; font-size:20px; color:#111827;">明日のリハビリ予約のお知らせ</h1>
    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">{{ $appointment->patient?->name }} 様</p>
    <p style="margin:12px 0 0; color:#374151; line-height:1.8;">明日のリハビリ予約についてお知らせします。</p>

    @include('emails.partials.reservation-detail', ['appointment' => $appointment])

    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">変更やキャンセルが必要な場合は、クリニックまでご連絡ください。</p>
@endsection
