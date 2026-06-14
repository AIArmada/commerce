<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Middleware;

use AIArmada\Affiliates\Services\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

final class CaptureAffiliateReferralFromPath
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = '/' . $request->path();

        if (! preg_match('#/r/([a-zA-Z0-9_\-]+)$#', $path, $matches)) {
            return $next($request);
        }

        $code = $matches[1];

        $affiliate = $this->affiliates->findByCode($code);

        if ($affiliate === null || ! $affiliate->isActive()) {
            return $next($request);
        }

        $cookieName = (string) config('affiliates.cookies.name', 'affiliate_session');
        $cookieValue = $request->cookie($cookieName);
        $context = $this->buildContext($request);
        $attribution = $this->affiliates->trackVisitByCode($code, $context, $cookieValue);

        if ($attribution === null || ! is_string($attribution->cookieValue) || $attribution->cookieValue === '') {
            return $next($request);
        }

        $prefix = mb_substr($path, 0, mb_strlen($path) - mb_strlen('/r/' . $code));
        $redirectPath = $prefix === '' ? '/' : $prefix;
        $redirectUrl = $redirectPath . ($request->getQueryString() ? '?' . $request->getQueryString() : '');

        return redirect($redirectUrl)
            ->withCookie(cookie(
                name: $cookieName,
                value: $attribution->cookieValue,
                minutes: (int) config('affiliates.cookies.ttl_minutes', 60 * 24 * 30),
                path: config('affiliates.cookies.path', '/'),
                domain: config('affiliates.cookies.domain'),
                secure: config('affiliates.cookies.secure'),
                httpOnly: config('affiliates.cookies.http_only', true),
                raw: false,
                sameSite: config('affiliates.cookies.same_site', 'lax'),
            ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Request $request): array
    {
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
            'metadata' => [
                'entry_route' => 'affiliate.referral.path',
                'utm' => Arr::only($request->query(), ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']),
            ],
        ];
    }
}
