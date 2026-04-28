<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Middleware;

use AIArmada\CommerceSupport\Events\OwnerNotResolvedForRequestEvent;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Http\Request;

final class NeedsOwner
{
    /**
     * @param  Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (OwnerContext::resolve() === null) {
            event(new OwnerNotResolvedForRequestEvent($request));

            throw NoCurrentOwnerException::forRequest($request);
        }

        return $next($request);
    }
}