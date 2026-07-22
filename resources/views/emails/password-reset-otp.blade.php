@php
    $locale = app()->getLocale();
    $direction = \App\Support\Locale::direction();
    $align = $direction === 'rtl' ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('notification.otp_subject') }} - Healix</title>
</head>
{{-- Inline styles only: email clients strip <style> blocks and external CSS. --}}
<body style="margin:0; padding:0; background-color:#f0f2f5; font-family:'Segoe UI','Noto Sans Arabic',Tahoma,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f2f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $direction }}"
                       style="max-width:560px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    <tr>
                        <td style="background-color:#0d9488; padding:22px 28px; text-align:{{ $align }};">
                            <h1 style="margin:0; color:#ffffff; font-size:19px; font-weight:600;">
                                {{ __('notification.otp_greeting') }}
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px; text-align:{{ $align }}; color:#1f2937; font-size:15px; line-height:1.7;">
                            <p style="margin:0 0 14px;">
                                {{ __('notification.otp_hello', ['name' => $user->full_name]) }}
                            </p>

                            <p style="margin:0 0 22px; color:#374151;">
                                {{ __('notification.otp_body') }}
                            </p>

                            {{-- The code stays LTR and unspaced so it can be copied
                                 verbatim, even inside an RTL document. --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 22px;">
                                <tr>
                                    <td dir="ltr"
                                        style="background-color:#f0fdfa; border:1px solid #99f6e4; border-radius:8px;
                                               padding:16px 34px; text-align:center;">
                                        <span style="font-family:'Courier New',Consolas,monospace; font-size:32px;
                                                     font-weight:700; letter-spacing:8px; color:#0f766e;">{{ $otp }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px; color:#6b7280; font-size:13px;">
                                {{ __('notification.otp_expiry', ['count' => $expiresInMinutes]) }}
                            </p>

                            <p style="margin:0; color:#b91c1c; font-size:13px;">
                                {{ __('notification.otp_warning') }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb;
                                   text-align:{{ $align }}; color:#6b7280; font-size:12px; line-height:1.6;">
                            {{ __('notification.otp_footer') }}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
