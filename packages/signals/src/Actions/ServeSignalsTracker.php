<?php

declare(strict_types=1);

namespace AIArmada\Signals\Actions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

final class ServeSignalsTracker
{
    use AsAction;

    public function asController(Request $request): Response
    {
        $trackerScriptPattern = preg_quote('/' . mb_ltrim((string) config('signals.http.tracker_script', 'tracker.js'), '/'), '/');

        $script = <<<'JS'
(function () {
  var script = document.currentScript;

  if (!script) {
    return;
  }

  var writeKey = script.dataset.writeKey;

  if (!writeKey) {
    console.warn('Signals tracker requires a data-write-key attribute.');
    return;
  }

  var trackerUrl = new URL(script.src, window.location.href);
  var endpoint = script.dataset.endpoint;

  if (!endpoint) {
    trackerUrl.pathname = trackerUrl.pathname.replace(/__TRACKER_SCRIPT_PATTERN__$/, '/collect/pageview');
    trackerUrl.search = '';
    trackerUrl.hash = '';
    endpoint = trackerUrl.toString();
  }

  var sessionKey = 'signals:session:' + writeKey;
  var startedAtKey = 'signals:session-started-at:' + writeKey;
  var lastUrl = null;

  function sessionIdentifier() {
    var existing = sessionStorage.getItem(sessionKey);

    if (existing) {
      return existing;
    }

    var created = 'sig_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    sessionStorage.setItem(sessionKey, created);
    sessionStorage.setItem(startedAtKey, new Date().toISOString());

    return created;
  }

  function sessionStartedAt() {
    var value = sessionStorage.getItem(startedAtKey);

    if (value) {
      return value;
    }

    var created = new Date().toISOString();
    sessionStorage.setItem(startedAtKey, created);

    return created;
  }

  function payload() {
    var params = new URLSearchParams(window.location.search);

    return {
      write_key: writeKey,
      session_identifier: sessionIdentifier(),
      session_started_at: sessionStartedAt(),
      occurred_at: new Date().toISOString(),
      path: window.location.pathname + window.location.search + window.location.hash,
      url: window.location.href,
      title: document.title || null,
      referrer: document.referrer || null,
      utm_source: params.get('utm_source'),
      utm_medium: params.get('utm_medium'),
      utm_campaign: params.get('utm_campaign'),
      utm_content: params.get('utm_content'),
      utm_term: params.get('utm_term')
    };
  }

  function sendPageView() {
    if (lastUrl === window.location.href) {
      return;
    }

    lastUrl = window.location.href;

    var body = JSON.stringify(payload());

    if (navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
      return;
    }

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: body,
      keepalive: true,
      credentials: 'omit'
    }).catch(function () {});
  }

  var originalPushState = history.pushState;
  history.pushState = function () {
    originalPushState.apply(history, arguments);
    setTimeout(sendPageView, 0);
  };

  var originalReplaceState = history.replaceState;
  history.replaceState = function () {
    originalReplaceState.apply(history, arguments);
    setTimeout(sendPageView, 0);
  };

  window.addEventListener('popstate', function () {
    setTimeout(sendPageView, 0);
  });

  sendPageView();
})();
JS;

        $script = str_replace('__TRACKER_SCRIPT_PATTERN__', $trackerScriptPattern, $script);

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
