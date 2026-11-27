<?php

declare(strict_types=1);

namespace AIArmada\Links\EntryPoints\Http;

use AIArmada\Links\Actions\RecordLinkClick;
use AIArmada\Links\Actions\ResolveLink;
use AIArmada\Links\Contracts\FallbackRedirectGateInterface;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Data\LinkGateContext;
use AIArmada\Links\Events\LinkBlocked;
use AIArmada\Links\Events\LinkClickLimitReached;
use AIArmada\Links\Events\LinkExpired;
use AIArmada\Links\Exceptions\LinkUnavailableException;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Throwable;

final class RedirectToLinkController
{
    public function branded(Request $request, string $prefix, string $slug): RedirectResponse
    {
        return $this->redirect($request, $slug);
    }

    public function redirect(Request $request, string $slug): RedirectResponse
    {
        $slug = mb_trim($slug);
        $link = Link::query()->withoutOwnerScope()->where('slug', $slug)->first();

        if (! $link instanceof Link || $link->slug_prefix !== $request->route('prefix')) {
            abort(404);
        }

        if ($link->require_signature && ! URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired signed URL.');
        }

        $reason = app(ResolveLink::class)->blockedReasonFor($link);

        if ($reason !== null) {
            return $this->blockedResponse($link, $reason);
        }

        try {
            app(RecordLinkClick::class)->handle($link);
        } catch (LinkUnavailableException $exception) {
            return $this->blockedResponse($link, $exception->reason);
        } catch (Throwable $exception) {
            report($exception);

            if ($link->max_clicks !== null) {
                abort(503, 'Unable to verify link availability.');
            }
        }

        if ($link->require_signature && ! URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired signed URL.');
        }

        return redirect()->to(
            $this->destinationWithParams($link, $request),
            (int) config('links.defaults.redirect_status', 302),
        );
    }

    private function blockedResponse(Link $link, string $reason): RedirectResponse
    {
        $this->fireBlockedEvent($link, $reason);

        $gate = app(LinkGateInterface::class);

        if ($gate instanceof FallbackRedirectGateInterface) {
            $fallback = $gate->fallbackUrl(new LinkGateContext((string) $link->getKey(), $link->slug, $link->subject_type, $link->subject_id), $reason);

            if (is_string($fallback) && $fallback !== '') {
                return redirect()->to($fallback, (int) config('links.defaults.redirect_status', 302));
            }
        }

        abort(410, 'Link is no longer available.');
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
            if (! isset($query[$key]) && isset($defaults[$key]) && is_string($defaults[$key]) && $defaults[$key] !== '') {
                $query[$key] = $defaults[$key];
            }
        }

        foreach ($request->query() as $key => $value) {
            if (
                ! is_string($key)
                || mb_strlen($key) > 100
                || preg_match('/\Autm_[A-Za-z0-9_]+\z/i', $key) !== 1
                || ! is_string($value)
                || $value === ''
                || mb_strlen($value) > 500
            ) {
                continue;
            }

            $query[$key] = $value;
        }

        // Ad click IDs pass through so merchants keep their own ad attribution.
        foreach (LinkAttributes::CLICK_ID_KEYS as $key) {
            $incoming = $request->query($key);

            if (is_string($incoming) && $incoming !== '' && mb_strlen($incoming) <= 500) {
                $query[$key] = $incoming;
            }
        }

        // Dynamic sub IDs let one link carry per-affiliate tracking through to
        // the merchant; explicit link parameters below still win.
        foreach (['sub1', 'sub2', 'sub3'] as $key) {
            $incoming = $request->query($key);

            if (is_string($incoming) && $incoming !== '' && mb_strlen($incoming) <= 255) {
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
