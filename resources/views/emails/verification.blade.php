@php
    $locale = app()->getLocale();
    $direction = \App\Support\Locale::direction();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('notification.verify_subject') }} - Healix</title>
</head>
<body dir="{{ $direction }}" style="text-align: {{ $direction === 'rtl' ? 'right' : 'left' }};">
    <div>
        <h1>{{ __('notification.verify_greeting') }}</h1>
    </div>

    <div>
        <h2>{{ __('notification.verify_hello', ['name' => $user->full_name]) }}</h2>

        <p>{{ __('notification.verify_body') }}</p>

        <div>
            <a href="{{ $verificationUrl }}" class="button">{{ __('notification.verify_action') }}</a>
        </div>

        <p>{{ __('notification.verify_fallback') }}</p>

        {{-- The URL itself always stays LTR, even inside an RTL document. --}}
        <p style="word-break: break-all; background-color: #e9e9e9; padding: 10px; border-radius: 4px;" dir="ltr">
            {{ $verificationUrl }}
        </p>

        <p>{{ __('notification.verify_footer') }}</p>
    </div>

</body>
</html>
