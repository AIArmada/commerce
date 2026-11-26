<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\EntryPoints\Middleware;

use Illuminate\Http\Request;

final readonly class ReferralTrackingConsent
{
    public function allows(Request $request): bool
    {
        if (! config('affiliates.cookies.enabled', true)) {
            return false;
        }

        if (config('affiliates.cookies.respect_dnt', false) && $request->headers->get('DNT') === '1') {
            return false;
        }

        if (! config('affiliates.cookies.require_consent', false)) {
            return true;
        }

        $cookie = (string) config('affiliates.cookies.consent_cookie', 'affiliate_consent');

        return $request->boolean('affiliate_consent')
            || filter_var($request->cookie($cookie), FILTER_VALIDATE_BOOLEAN);
    }
}
