<!doctype html>
<html lang="en">
<head>
    <script>
      (function(){
        var t=localStorage.getItem('dtr_theme');
        if(t){document.documentElement.setAttribute('data-theme',t);}
        else if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){
          document.documentElement.setAttribute('data-theme','dark');
        }
      })();
    </script>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>UDTRMS — University Daily Time Record Monitoring System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased font-sans">
    <div id="app"></div>
</body>
</html>
