<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkBlocked;
use AIArmada\Links\Events\LinkClickLimitReached;
use AIArmada\Links\Events\LinkExpired;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class RedirectToLink
{
    use AsAction;

    public function asController(Request $request, string $slug): RedirectResponse
    {
        $slug = mb_trim($slug);
        $link = Link::query()->withoutOwnerScope()->where('slug', $slug)->first();

        if (! $link instanceof Link) {
            abort(404);
        }

        if ($link->require_signature && ! URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired signed URL.');
        }

        $reason = app(ResolveLink::class)->blockedReason($slug);

        if ($reason !== null) {
            $this->fireBlockedEvent($link, $reason);

            abort(410, 'Link is no longer available.');
        }

        try {
            app(RecordLinkClick::class)->handle($link);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->to(
            $this->destinationWithParams($link, $request),
            (int) config('links.defaults.redirect_status', 302),
        );
    }

    private function fireBlockedEvent(Link $link, string $reason): void
    {
        event(match ($reason) {
            'expired' => new LinkExpired($link),
            'limit_reached' => new LinkClickLimitReached($link),
            default => new LinkBlocked($link, $reason),
        });
    }

    private function destinationWithParams(Link $link, Request $request): string
    {
        $parts = parse_url($link->destination_url) ?: [];
        $query = [];

        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $defaults = is_array($link->utm_defaults) ? $link->utm_defaults : [];

        foreach (LinkAttributes::UTM_KEYS as $key) {
            $incoming = $request->query($key);

            if (is_string($incoming) && $incoming !== '') {
                $query[$key] = $incoming;
            } elseif (! isset($query[$key]) && isset($defaults[$key]) && is_string($defaults[$key]) && $defaults[$key] !== '') {
                $query[$key] = $defaults[$key];
            }
        }

        // Ad click IDs pass through so merchants keep their own ad attribution.
        foreach (LinkAttributes::CLICK_ID_KEYS as $key) {
            $incoming = $request->query($key);

            if (is_string($incoming) && $incoming !== '') {
                $query[$key] = $incoming;
            }
        }

        // Link parameters are the owner's explicit intent: they always win,
        // so request query strings can never spoof attribution values.
        $parameters = is_array($link->parameters) ? $link->parameters : [];

        foreach ($parameters as $key => $value) {
            if (is_string($key) && $key !== '' && is_string($value) && $value !== '') {
                $query[$key] = $value;
            }
        }

        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }
}
