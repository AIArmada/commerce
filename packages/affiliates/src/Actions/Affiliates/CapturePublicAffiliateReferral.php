<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CapturePublicAffiliateReferral
{
    use AsAction;

    public function __construct(
        private readonly AffiliateService $affiliates,
    ) {}

    public function handle(Request $request, string $affiliateCode): RedirectResponse
    {
        if (! config('affiliates.public_pages.enabled', true) || ! config('affiliates.public_pages.route.enabled', true)) {
            throw new NotFoundHttpException;
        }

        $affiliate = $this->affiliates->findByCode($affiliateCode);

        if ($affiliate === null || ! $affiliate->isActive()) {
            throw new NotFoundHttpException;
        }

        $cookieName = (string) config('affiliates.cookies.name', 'affiliate_session');
        $cookieValue = $request->cookie($cookieName);
        $attribution = $this->affiliates->trackVisitByCode($affiliate->code, $this->buildContext($request), $cookieValue);

        if ($attribution === null || ! is_string($attribution->cookieValue) || $attribution->cookieValue === '') {
            throw new NotFoundHttpException;
        }

        return redirect($this->destinationUrl($request))
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
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

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
                'entry_route' => (string) config('affiliates.public_pages.route.name', 'affiliate.referral.entry'),
                'utm' => Arr::only($request->query(), $utmKeys),
            ],
        ];
    }

    private function destinationUrl(Request $request): string
    {
        $destinationKey = (string) $request->query(
            (string) config('affiliates.public_pages.route.destination_parameter', 'to'),
            'home',
        );

        $destination = config(
            'affiliates.public_pages.route.destinations.' . $destinationKey,
            config('affiliates.public_pages.route.destinations.home', '/'),
        );

        return $this->normalizeDestinationUrl($destination);
    }

    private function normalizeDestinationUrl(mixed $destination): string
    {
        if (! is_string($destination) || $destination === '') {
            return url('/');
        }

        return Str::startsWith($destination, ['http://', 'https://'])
            ? $destination
            : url($destination);
    }
}
