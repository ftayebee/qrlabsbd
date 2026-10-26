<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $brand }}</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #F7F8F2; color: #18251B; font: 16px/1.6 Inter, system-ui, -apple-system, sans-serif; padding: 24px; box-sizing: border-box; }
        .card { max-width: 420px; width: 100%; background: #fff; border: 1px solid #E3E6DC; border-radius: 20px; padding: 32px; text-align: center; box-shadow: 0 8px 24px -12px rgba(20,37,26,.12); }
        .mark { width: 48px; height: 48px; border-radius: 14px; background: #E7F5E9; display: grid; place-items: center; margin: 0 auto 16px; }
        h1 { font-size: 20px; margin: 0 0 8px; letter-spacing: -0.01em; }
        p { margin: 0 0 20px; color: #687469; }
        a { display: inline-block; background: #1DB45A; color: #fff; text-decoration: none; font-weight: 600; padding: 10px 18px; border-radius: 12px; }
        a:focus-visible { outline: 2px solid #14251A; outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#12803E" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/></svg>
        </div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ $home }}">Visit {{ $brand }}</a>
    </main>
</body>
</html>
