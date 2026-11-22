// Real-time updates (Laravel Echo over Reverb), loaded on demand.
//
// Echo and pusher-js are only needed once someone is signed in, so they
// are fetched the first time a page asks for them instead of being part
// of the main bundle — the login page no longer downloads and parses them.
// Every caller shares one connection; window.Echo is still set for any
// code that checks it directly.
let echoPromise = null;

export function getEcho() {
    if (!echoPromise) {
        echoPromise = Promise.all([import('laravel-echo'), import('pusher-js')])
            .then(([{ default: Echo }, { default: Pusher }]) => {
                window.Pusher = Pusher;
                window.Echo = new Echo({
                    broadcaster: 'reverb',
                    key:          import.meta.env.VITE_REVERB_APP_KEY,
                    wsHost:       import.meta.env.VITE_REVERB_HOST,
                    wsPort:       import.meta.env.VITE_REVERB_PORT  ?? 80,
                    wssPort:      import.meta.env.VITE_REVERB_PORT  ?? 443,
                    forceTLS:    (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                    enabledTransports: ['ws', 'wss'],
                });
                return window.Echo;
            })
            .catch(() => {
                // Chunk failed to load (offline, mid-deploy) — live updates
                // are optional, so pages carry on without them and the next
                // call tries again.
                echoPromise = null;
                return null;
            });
    }
    return echoPromise;
}
