<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Middleware\SetExplicitGlobalOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\Request;

it('marks the current request as explicit global owner context', function (): void {
    $middleware = new SetExplicitGlobalOwnerContext;
    $request = Request::create('/public-checkout', 'GET');
    $previousRequest = app()->bound('request') ? app('request') : null;
    app()->instance('request', $request);

    try {
        $nextCalled = false;

        $response = $middleware->handle($request, function (Request $incoming) use (&$nextCalled) {
            $nextCalled = true;

            expect($incoming->path())->toBe('public-checkout')
                ->and(OwnerContext::hasOverride())->toBeTrue()
                ->and(OwnerContext::isExplicitGlobal())->toBeTrue()
                ->and(OwnerContext::resolve())->toBeNull();

            return response('OK');
        });

        expect($nextCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200);
    } finally {
        if ($previousRequest instanceof Request) {
            app()->instance('request', $previousRequest);
        } else {
            app()->forgetInstance('request');
        }
    }
});
