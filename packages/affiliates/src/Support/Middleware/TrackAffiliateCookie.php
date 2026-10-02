<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Middleware;

use AIArmada\Affiliates\Actions\Affiliates\TouchAffiliateAttribution;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

final class TrackAffiliateCookie
{
    public function __construct(
        private readonly TrackAffiliateVisit $trackAffiliateVisit,
        private readonly TouchAffiliateAttribution $touchAffiliateAttribution,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('affiliates.cookies.enabled', true)) {
            return $next($request);
        }

        if ($this->shouldRespectDoNotTrack($request)) {
            return $next($request);
        }

        if ($this->requiresConsent() && ! $this->hasConsent($request)) {
            return $next($request);
        }

        /** @var Response $response */
        $response = $next($request);

        $cookieName = config('affiliates.cookies.name', 'affiliate_session');
        $cookieValue = $request->cookie($cookieName);
        $linkId = $request->query('aff_link');
        $link = is_string($linkId) ? AffiliateLink::query()->withoutGlobalScope('affiliate_owner')
            ->with(['affiliate' => fn ($query) => $query->withoutOwnerScope()])
            ->whereKey($linkId)->whereNull('deactivated_at')->first() : null;
        $affiliateCode = $linkId !== null ? $link?->affiliate->code : $this->resolveAffiliateCode($request);
        $context = $this->buildContext($request);

        if ($affiliateCode) {
            if ($link !== null) {
                $context = array_merge($context, [
                    'affiliate_link_id' => $link->id,
                    'subject_type' => $link->subject_type,
                    'subject_key' => $link->subject_key,
                    'subject_id' => $link->subject_id,
                    'subject_instance' => $link->subject_instance,
                    'subject_title_snapshot' => $link->subject_title_snapshot,
                    'metadata' => array_merge($link->subject_metadata ?? [], $context['metadata']),
                    'origin' => $link->origin,
                ]);
                $owner = OwnerContext::fromTypeAndId($link->affiliate->owner_type, $link->affiliate->owner_id);
                $attribution = OwnerContext::withOwner($owner, fn () => $this->trackAffiliateVisit->handle($affiliateCode, $context, $cookieValue));
            } else {
                $attribution = $this->trackAffiliateVisit->handle($affiliateCode, $context, $cookieValue);
            }
            $cookieValue = $attribution?->cookieValue;
        } elseif ($cookieValue) {
            $this->touchAffiliateAttribution->handle($cookieValue, $context);
        }

        if (! $cookieValue) {
            return $response;
        }

        $minutes = (int) config('affiliates.cookies.ttl_minutes', 60 * 24 * 30);

        $response->headers->setCookie(cookie(
            name: $cookieName,
            value: $cookieValue,
            minutes: $minutes,
            path: config('affiliates.cookies.path', '/'),
            domain: config('affiliates.cookies.domain'),
            secure: config('affiliates.cookies.secure'),
            httpOnly: config('affiliates.cookies.http_only', true),
            raw: false,
            sameSite: config('affiliates.cookies.same_site', 'lax')
        ));

        return $response;
    }

    private function resolveAffiliateCode(Request $request): ?string
    {
        $keys = (array) config('affiliates.cookies.query_parameters', ['aff']);

        foreach ($keys as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Request $request): array
    {
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $querySnapshot = Arr::only($request->query(), $utmKeys);

        return [
            'source' => $request->query('utm_source'),
            'medium' => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
            'term' => $request->query('utm_term'),
            'content' => $request->query('utm_content'),
            'landing_url' => $request->fullUrl(),
            'referrer_url' => $request->headers->get('referer'),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'metadata' => ['utm' => $querySnapshot],
        ];
    }

    private function shouldRespectDoNotTrack(Request $request): bool
    {
        if (! config('affiliates.cookies.respect_dnt', false)) {
            return false;
        }

        return (string) $request->headers->get('DNT') === '1';
    }

    private function requiresConsent(): bool
    {
        return (bool) config('affiliates.cookies.require_consent', false);
    }

    private function hasConsent(Request $request): bool
    {
        if (! $this->requiresConsent()) {
            return true;
        }

        $consentCookie = (string) config('affiliates.cookies.consent_cookie', 'affiliate_consent');

        if ($request->boolean('affiliate_consent')) {
            return true;
        }

        return (bool) $request->cookie($consentCookie);
    }
}
