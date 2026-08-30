@php
    $locale = \App\Support\Locale::current();
    $direction = \App\Support\Locale::direction($locale);
    $deepLink = null;
    if (! empty($token)) {
        $deepLink = 'healix://verify-email?token=' . rawurlencode($token);
        if (! empty($role)) {
            $deepLink .= '&role=' . rawurlencode($role);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ __('auth.verify_open_app_title') }}</title>
</head>
<body dir="{{ $direction }}">
  <script>
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const role = params.get('role');
    if (token) {
      let appUrl = 'healix://verify-email?token=' + encodeURIComponent(token);
      if (role) {
        appUrl += '&role=' + encodeURIComponent(role);
      }
      window.location.href = appUrl;
    }

    setTimeout(() => {
      const fallback = document.getElementById('fallback');
      if (fallback) {
        fallback.style.display = 'block';
      }
    }, 2000);
  </script>
  <div id="fallback" style="display:none; text-align:center; padding:40px;">
    @if($deepLink)
      <p>{{ __('auth.verify_open_app_fallback') }}</p>
      <a href="{{ $deepLink }}">{{ __('auth.verify_open_app_action') }}</a>
    @else
      <p>{{ __('auth.verification_link_invalid') }}</p>
    @endif
  </div>
</body>
</html>
