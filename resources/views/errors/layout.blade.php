{{--
    Shared layout for every server-rendered error page (403, 404, 419, 429,
    500, 503 — including maintenance mode).

    Deliberately self-contained: no database queries (the app shell's own
    branding lookup is often exactly what failed), no Vite build assets, no
    inline JavaScript (blocked by the Content-Security-Policy). Styling is
    inline; /theme-init.js applies the user's saved dark-mode choice, the
    same way the app does.
--}}
<!doctype html>
<html lang="en">
<head>
    <script src="/theme-init.js"></script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>@yield('code') · @yield('title') — UDTRMS</title>
    <link rel="icon" href="/nemsu.png" />
    <style>
        :root {
            --bg: #F4F6FB;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text: #0F172A;
            --muted: #5B6B86;
            --navy: #2E4A8C;
            --navy-deep: #1B2A5E;
            --gold: #C9A227;
            --accent: @yield('accent', '#2E4A8C');
        }
        [data-theme="dark"] {
            --bg: #121B2E;
            --card: #1C2A44;
            --border: rgba(255, 255, 255, 0.10);
            --text: #E4EAF5;
            --muted: #9AAAC9;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100%;
            padding: 24px 16px;
            background: var(--bg);
            color: var(--text);
            font-family: "IBM Plex Sans", "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .brand img {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            box-shadow: 0 0 0 2px var(--gold);
        }
        .card {
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            text-align: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
        }
        .card-bar { height: 4px; background: linear-gradient(90deg, var(--navy) 0%, var(--navy) 55%, var(--gold) 100%); }
        .card-body { padding: 34px 30px 30px; }
        .code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            padding: 6px 14px;
            margin-bottom: 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: var(--accent);
            background: color-mix(in srgb, var(--accent) 12%, transparent);
        }
        [data-theme="dark"] .code { color: color-mix(in srgb, var(--accent) 45%, #FFFFFF); background: color-mix(in srgb, var(--accent) 28%, transparent); }
        h1 { margin: 0 0 10px; font-size: 22px; line-height: 1.3; }
        p  { margin: 0 auto; max-width: 360px; font-size: 14.5px; line-height: 1.6; color: var(--muted); }
        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 26px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .btn-primary { color: #FFFFFF; background: var(--navy); border: 1px solid var(--navy); }
        .btn-primary:hover { background: var(--navy-deep); }
        .btn-ghost { color: var(--text); background: transparent; border: 1px solid var(--border); }
        .btn-ghost:hover { border-color: var(--muted); }
        [data-theme="dark"] .btn-primary { background: #3B5BA9; border-color: #3B5BA9; }
        .foot { margin-top: 18px; font-size: 12px; color: var(--muted); text-align: center; }
        @media (max-width: 420px) {
            .card-body { padding: 28px 20px 24px; }
            .btn { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <div class="brand">
        <img src="/nemsu.png" alt="" />
        <span>NEMSU · Daily Time Record System</span>
    </div>

    <main class="card">
        <div class="card-bar"></div>
        <div class="card-body">
            <div class="code">Error @yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="actions">
                @yield('actions')
            </div>
        </div>
    </main>

    <div class="foot">North Eastern Mindanao State University</div>
</body>
</html>
