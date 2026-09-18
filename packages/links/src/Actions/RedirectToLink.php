<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkClickLimitReached;
use AIArmada\Links\Events\LinkExpired;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class RedirectToLink
{
    use AsAction;

    public function asController(Request $request, string $slug): RedirectResponse
    {
        $link = app(ResolveLink::class)->handle($slug);

        if (! $link instanceof Link) {
            $this->fireBlockedEvent($slug);

            abort(404);
        }

        try {
            app(RecordLinkClick::class)->handle($link);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->to(
            $this->destinationWithUtm($link, $request),
            (int) config('links.defaults.redirect_status', 302),
        );
    }

    private function fireBlockedEvent(string $slug): void
    {
        $reason = app(ResolveLink::class)->blockedReason($slug);

        if ($reason !== 'expired' && $reason !== 'limit_reached') {
            return;
        }

        $link = Link::query()->withoutOwnerScope()->where('slug', mb_trim($slug))->first();

        if (! $link instanceof Link) {
            return;
        }

        event($reason === 'expired' ? new LinkExpired($link) : new LinkClickLimitReached($link));
    }

    private function destinationWithUtm(Link $link, Request $request): string
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
