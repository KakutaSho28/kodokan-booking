@extends('emails.layout', ['title' => 'リハビリ予約確定のお知らせ'])

@section('content')
    <h1 style="margin:0; font-size:20px; color:#111827;">リハビリ予約を確定しました</h1>
    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">{{ $appointment->patient?->name }} 様</p>
    <p style="margin:12px 0 0; color:#374151; line-height:1.8;">以下の内容でリハビリ予約を受け付けました。</p>

    @include('emails.partials.reservation-detail', ['appointment' => $appointment])

    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">当日は診察券をお持ちのうえ、予約時間までに受付へお越しください。</p>
@endsection
