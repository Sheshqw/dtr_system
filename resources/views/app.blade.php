<!doctype html>
<html lang="en">
<head>
    <script src="/theme-init.js"></script>
    @php
        $__settings = \App\Models\SystemSetting::current();
        $__favicon  = $__settings->logo_url ?? '/nemsu.png';
    @endphp
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $__settings->short_name }} — {{ $__settings->tagline }}</title>
    <link rel="icon" href="{{ $__favicon }}?v={{ $__settings->updated_at?->timestamp }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    {{-- Every weight the UI actually uses (font-bold is 700) — a missing
         weight makes the browser smear a fake bold from the 600. The serif
         is for formal headings, like the university name on the login page. --}}
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=IBM+Plex+Serif:wght@600;700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased font-sans">
    <div id="app"></div>
</body>
</html>
