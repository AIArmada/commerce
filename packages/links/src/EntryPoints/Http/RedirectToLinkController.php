<?php

declare(strict_types=1);

namespace AIArmada\Links\EntryPoints\Http;

use AIArmada\Links\Actions\BuildLinkDestination;
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

        return redirect()->to(
            app(BuildLinkDestination::class)->handle($link, $request->query()),
            (int) config('links.defaults.redirect_status', 302),
        );
    }

    private function blockedResponse(Link $link, string $reason): RedirectResponse
    {
        $this->fireBlockedEvent($link, $reason);

        $gate = app(LinkGateInterface::class);

        if (! in_array($reason, ['owner_changed', 'owner_unknown', 'link_changed', 'link_unknown'], true) && $gate instanceof FallbackRedirectGateInterface) {
            $fallback = $gate->fallbackUrl(new LinkGateContext((string) $link->getKey(), $link->slug, $link->subject_type, $link->subject_id), $reason);

            if (is_string($fallback) && $this->isSafeFallback($fallback)) {
                return redirect()->to($fallback, (int) config('links.defaults.redirect_status', 302));
            }
        }

        abort(410, 'Link is no longer available.');
    }

    private function isSafeFallback(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(mb_strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    private function fireBlockedEvent(Link $link, string $reason): void
    {
        event(match ($reason) {
            'expired' => new LinkExpired($link),
            'limit_reached' => new LinkClickLimitReached($link),
            default => new LinkBlocked($link, $reason),
        });
    }
}
