// Used by offline.html, which the service worker shows when a page load
// fails. A separate file rather than an inline script so it complies with
// the app's Content-Security-Policy.
(function () {
  var RETRY_MS = 20000;

  // A failed page load usually means the device is offline — but if the
  // device reports a working connection, it's the server that isn't
  // answering (restarting, or the host is down). Say so, and retry on a
  // timer, since no "online" event will come in that case.
  if (navigator.onLine) {
    document.title = "Can't reach the system — UDTRMS";
    document.getElementById('heading').textContent = "Can't reach the system right now";
    document.getElementById('message').textContent =
      "Your internet connection is working, but the system isn't responding. It may be restarting or under maintenance. This page tries again automatically.";
    document.getElementById('tips').hidden = true;
    // Server icon instead of "no Wi-Fi" — the device's connection is fine.
    document.getElementById('icon').innerHTML =
      '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/>' +
      '<path d="M6 6h.01"/><path d="M6 18h.01"/></svg>';
    setInterval(function () { window.location.reload(); }, RETRY_MS);
  }

  // Back online: load the page the user was actually trying to open.
  window.addEventListener('online', function () { window.location.reload(); });
})();
