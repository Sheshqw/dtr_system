<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline defense-in-depth headers on every response. None of these
 * replace real controls (auth, validation) — they narrow what a browser
 * will do if something else ever goes wrong (XSS, clickjacking, MIME
 * sniffing a malicious upload as executable content).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // script-src has no unsafe-inline and only one external host (below),
        // so an injected <script> tag or javascript: URI simply won't execute,
        // even if some future bug lets an attacker get markup into the page.
        // style-src needs unsafe-inline: the app sets a lot of literal
        // style="color:#…" attributes directly, and Google Fonts' stylesheet
        // link needs its own host. connect-src allows any ws(s): host since
        // the Reverb broadcast server's LAN IP changes with the network.
        // challenges.cloudflare.com is the one external script/frame host:
        // the Cloudflare Turnstile login widget loads its script from there
        // and renders inside an iframe from the same origin.
        // While `npm run dev` is running, the page's scripts and styles come
        // from the Vite dev server on another port; without allowing it the
        // whole app renders blank. Only true when public/hot exists, which
        // never ships to production.
        $vite = Vite::isRunningHot() ? ' ' . trim(file_get_contents(Vite::hotFile())) : '';

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://challenges.cloudflare.com{$vite}",
            "frame-src https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$vite}",
            "font-src 'self' https://fonts.gstatic.com{$vite}",
            "img-src 'self' data: blob:{$vite}",
            "connect-src 'self' ws: wss:",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ]));

        return $response;
    }
}
