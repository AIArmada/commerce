<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Http\Middleware;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate affiliate portal requests.
 * Uses bearer token or session-based authentication.
 */
final class AuthenticateAffiliate
{
    public function handle(Request $request, Closure $next): Response
    {
        $affiliate = $this->resolveAffiliate($request);

        if (! $affiliate) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('affiliate.login');
        }

        if (! $affiliate->isActive()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account is not active.'], 403);
            }

            return redirect()->route('affiliate.login')
                ->with('error', 'Your affiliate account is not active.');
        }

        $request->attributes->set('affiliate', $affiliate);

        $owner = OwnerContext::fromTypeAndId($affiliate->owner_type, $affiliate->owner_id);

        return OwnerContext::withOwner($owner, fn (): Response => $next($request));
    }

    private function resolveAffiliate(Request $request): ?Affiliate
    {
        // Try bearer token first
        $token = $request->bearerToken();
        if ($token) {
            return Affiliate::query()
                ->withoutOwnerScope()
                ->where('api_token', hash('sha256', $token))
                ->where('status', 'active')
                ->first();
        }

        // Try session-based authentication
        $affiliateId = $request->session()->get('affiliate_id');
        if ($affiliateId) {
            return Affiliate::query()->withoutOwnerScope()->find($affiliateId);
        }

        // Try custom resolver from config
        $resolverClass = config('affiliates.portal.auth_resolver');
        if ($resolverClass && class_exists($resolverClass)) {
            $resolver = app($resolverClass);

            return $resolver->resolve($request);
        }

        return null;
    }
}
