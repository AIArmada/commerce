<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Services\AffiliateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolvePublicAffiliateReferralContext
{
    use AsAction;

    public function __construct(
        private readonly AffiliateService $affiliates,
    ) {}

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     default_voucher_code: string|null,
     *     checkout_url: string,
     *     home_url: string,
     *     entry_url: string,
     *     source: string
     * }|null
     */
    public function handle(?Request $request = null): ?array
    {
        if (! config('affiliates.public_pages.enabled', true)) {
            return null;
        }

        if ($request === null) {
            if (! app()->bound('request')) {
                return null;
            }

            $request = request();
        }

        $resolvedAffiliate = $this->resolveFromQuery($request) ?? $this->resolveFromCookie($request);

        if ($resolvedAffiliate === null) {
            return null;
        }

        return [
            'code' => $resolvedAffiliate['code'],
            'name' => $resolvedAffiliate['name'],
            'default_voucher_code' => $resolvedAffiliate['default_voucher_code'],
            'checkout_url' => $this->destinationUrl('checkout', $resolvedAffiliate['code']),
            'home_url' => $this->destinationUrl('home', $resolvedAffiliate['code']),
            'entry_url' => url('/r/' . $resolvedAffiliate['code']),
            'source' => $resolvedAffiliate['source'],
        ];
    }

    /**
     * @return array{code: string, name: string, default_voucher_code: string|null, source: string}|null
     */
    private function resolveFromQuery(Request $request): ?array
    {
        foreach ((array) config('affiliates.cookies.query_parameters', ['aff']) as $parameter) {
            $affiliateCode = $request->query($parameter);

            if (! is_string($affiliateCode) || mb_trim($affiliateCode) === '') {
                continue;
            }

            $affiliate = $this->affiliates->findByCode($affiliateCode);

            if ($affiliate === null || ! $affiliate->isActive()) {
                continue;
            }

            return [
                'code' => (string) $affiliate->code,
                'name' => (string) $affiliate->name,
                'default_voucher_code' => $affiliate->default_voucher_code,
                'source' => 'query',
            ];
        }

        return null;
    }

    /**
     * @return array{code: string, name: string, default_voucher_code: string|null, source: string}|null
     */
    private function resolveFromCookie(Request $request): ?array
    {
        $cookieValue = $request->cookie((string) config('affiliates.cookies.name', 'affiliate_session'));

        if (! is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        $affiliate = $this->affiliates->findAffiliateByCookie($cookieValue);

        if ($affiliate === null || ! $affiliate->isActive()) {
            return null;
        }

        return [
            'code' => (string) $affiliate->code,
            'name' => (string) $affiliate->name,
            'default_voucher_code' => $affiliate->default_voucher_code,
            'source' => 'cookie',
        ];
    }

    private function destinationUrl(string $destinationKey, string $affiliateCode): string
    {
        $destination = config(
            'affiliates.public_pages.route.destinations.' . $destinationKey,
            $destinationKey === 'checkout' ? '/checkout' : '/',
        );

        return $this->appendQuery(
            $this->normalizeDestinationUrl($destination),
            [(string) config('affiliates.links.parameter', 'aff') => $affiliateCode],
        );
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

    /**
     * @param  array<string, string>  $query
     */
    private function appendQuery(string $url, array $query): string
    {
        $queryString = http_build_query(array_filter($query, static fn (?string $value): bool => $value !== null && $value !== ''));

        if ($queryString === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . $queryString;
    }
}
