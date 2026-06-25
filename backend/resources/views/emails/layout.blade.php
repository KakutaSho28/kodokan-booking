<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? '講道館ビルクリニック' }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; color:#111827; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
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
                        <td style="padding:24px;">
                            @yield('content')
                        </td>
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
</html>
