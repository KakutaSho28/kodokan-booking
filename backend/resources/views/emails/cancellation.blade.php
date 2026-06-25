@extends('emails.layout', ['title' => 'リハビリ予約キャンセルのお知らせ'])

@section('content')
    <h1 style="margin:0; font-size:20px; color:#111827;">リハビリ予約をキャンセルしました</h1>
    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">{{ $appointment->patient?->name }} 様</p>
    <p style="margin:12px 0 0; color:#374151; line-height:1.8;">以下の予約のキャンセルが完了しました。</p>

    @include('emails.partials.reservation-detail', ['appointment' => $appointment])

    <p style="margin:16px 0 0; color:#374151; line-height:1.8;">改めて予約を希望される場合は、予約画面から空き枠をご確認ください。</p>
@endsection
