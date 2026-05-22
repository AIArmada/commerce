<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Middleware;

use AIArmada\Affiliates\Actions\Affiliates\ResolvePublicAffiliateReferralContext;
use Closure;
use Illuminate\Http\Request;

final class HydratePublicAffiliateReferralContext
{
    public function __construct(
        private readonly ResolvePublicAffiliateReferralContext $resolvePublicAffiliateReferralContext,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $request->attributes->set(
            self::requestAttributeKey(),
            $this->resolvePublicAffiliateReferralContext->handle($request),
        );

        return $next($request);
    }

    public static function requestAttributeKey(): string
    {
        return 'affiliates.public_referral_context';
    }
}