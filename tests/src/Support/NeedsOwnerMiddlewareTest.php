<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Events\OwnerNotResolvedForRequestEvent;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Middleware\NeedsOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

describe('NeedsOwner middleware', function (): void {
    it('allows the request when an owner context is resolved', function (): void {
        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-allowed';
            }
        };

        $middleware = new NeedsOwner;
        $request = Request::create('/tenant/orders', 'GET');

        $nextCalled = false;

        OwnerContext::withOwner($owner, function () use ($middleware, $request, &$nextCalled): void {
            $response = $middleware->handle($request, function (Request $incoming) use (&$nextCalled) {
                $nextCalled = true;

                return response('OK');
            });

            expect($response->getStatusCode())->toBe(200);
        });

        expect($nextCalled)->toBeTrue();
    });

    it('throws and dispatches event when owner context is missing', function (): void {
        Event::fake([OwnerNotResolvedForRequestEvent::class]);

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });

        $middleware = new NeedsOwner;
        $request = Request::create('/tenant/orders', 'GET');

        expect(fn () => $middleware->handle($request, fn () => response('OK')))
            ->toThrow(NoCurrentOwnerException::class, 'No current owner could be resolved');

        Event::assertDispatched(OwnerNotResolvedForRequestEvent::class, function (OwnerNotResolvedForRequestEvent $event): bool {
            return $event->request->path() === 'tenant/orders';
        });
    });
});
