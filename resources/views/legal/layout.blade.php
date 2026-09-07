<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — CityUnlock</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="{{ $canonical }}">
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111827; background: #f9fafb; line-height: 1.6; }
        a { color: #ea580c; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 28px 18px 64px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 28px 24px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        h1 { margin: 0 0 8px; font-size: 1.875rem; line-height: 1.2; }
        h2 { margin: 28px 0 10px; font-size: 1.125rem; }
        p, li { color: #374151; font-size: .975rem; }
        .muted { color: #6b7280; font-size: .875rem; }
        .top { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 18px; }
        .brand { font-weight: 700; color: #ea580c; text-decoration: none; }
        .nav a { margin-left: 14px; text-decoration: none; color: #4b5563; font-size: .9rem; }
        ul { padding-left: 1.2rem; }
        li { margin: .35rem 0; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <a class="brand" href="{{ url('/') }}">CityUnlock</a>
            <div class="nav">
                <a href="{{ url('/privacy') }}">Privacy</a>
                <a href="{{ url('/terms') }}">Terms</a>
                <a href="{{ url('/contact') }}">Contact</a>
            </div>
        </div>
        <article class="card">
            @yield('content')
        </article>
    </div>
</body>
</html>
