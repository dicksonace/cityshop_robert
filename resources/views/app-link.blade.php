<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $share['title'] }}</title>
    <meta name="description" content="{{ $share['description'] }}">
    <meta property="og:type" content="{{ $share['type'] }}">
    <meta property="og:site_name" content="CityShop">
    <meta property="og:title" content="{{ $share['title'] }}">
    <meta property="og:description" content="{{ $share['description'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $share['image'] }}">
    <meta property="og:image:secure_url" content="{{ $share['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $share['title'] }}">
    <meta name="twitter:description" content="{{ $share['description'] }}">
    <meta name="twitter:image" content="{{ $share['image'] }}">
    <style>
        body { font-family: system-ui, sans-serif; background: #fff7ed; color: #1c1917; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 420px; width: 100%; background: #fff; border-radius: 20px; padding: 28px 24px; box-shadow: 0 10px 30px rgba(234, 88, 12, .12); text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { color: #78716c; font-size: .95rem; line-height: 1.45; }
        a { display: inline-block; margin-top: 16px; background: #ea580c; color: #fff; text-decoration: none; font-weight: 700; padding: 12px 18px; border-radius: 999px; }
        .web { background: transparent; color: #c2410c; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $heading }}</h1>
        <p>Opening this {{ $kind }} in the CityShop app…</p>
        <a href="{{ $appUrl }}">Open in app</a>
        <a class="web" href="{{ $webUrl }}">Continue on the website</a>
    </div>
    <script>
        (function () {
            var ua = navigator.userAgent || '';
            if (/bot|crawl|spider|facebookexternalhit|WhatsApp|Twitterbot|Slackbot|LinkedInBot|Telegram/i.test(ua)) {
                return;
            }
            var isAndroid = /Android/i.test(ua);
            var appUrl = @json($appUrl);
            var webUrl = @json($webUrl);
            var intentUrl = @json($androidIntent);
            window.location.href = isAndroid ? intentUrl : appUrl;
            window.setTimeout(function () {
                window.location.replace(webUrl);
            }, 1600);
        })();
    </script>
</body>
</html>
