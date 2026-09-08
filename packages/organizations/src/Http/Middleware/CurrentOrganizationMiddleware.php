<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Http\Middleware;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Organizations\Contracts\CurrentOrganizationResolver;
use Closure;
use Illuminate\Http\Request;

final class CurrentOrganizationMiddleware
{
    public function handle(Request $request, Closure $next, ?string $required = null): mixed
    {
        $contextRequired = $required === null
            ? (bool) config('organizations.middleware.require_context', true)
            : filter_var($required, FILTER_VALIDATE_BOOLEAN);

        if (! $contextRequired) {
            return OwnerContext::withOwner(null, static fn (): mixed => $next($request));
        }

        $organization = app(CurrentOrganizationResolver::class)->resolve();

        if ($organization === null) {
            throw new NoCurrentOwnerException('An organization context is required for this request.');
        }

        return OwnerContext::withOwner($organization, static fn (): mixed => $next($request));
    }
}
