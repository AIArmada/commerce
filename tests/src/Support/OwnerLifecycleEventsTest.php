<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Events\ForgettingCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\ForgotCurrentOwnerEvent;
use AIArmada\CommerceSupport\Events\MadeOwnerCurrentEvent;
use AIArmada\CommerceSupport\Events\MakingOwnerCurrentEvent;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

describe('Owner lifecycle events', function (): void {
    it('dispatches lifecycle events when using withOwner', function (): void {
        Event::fake([
            MakingOwnerCurrentEvent::class,
            MadeOwnerCurrentEvent::class,
            ForgettingCurrentOwnerEvent::class,
            ForgotCurrentOwnerEvent::class,
        ]);

        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-evt-1';
            }
        };

        OwnerContext::withOwner($owner, function (): void {
            expect(OwnerContext::resolve())->not->toBeNull();
        });

        Event::assertDispatched(MakingOwnerCurrentEvent::class, function (MakingOwnerCurrentEvent $event) use ($owner): bool {
            return $event->owner->getMorphClass() === $owner->getMorphClass()
                && (string) $event->owner->getKey() === (string) $owner->getKey();
        });

        Event::assertDispatched(MadeOwnerCurrentEvent::class);
        Event::assertDispatched(ForgettingCurrentOwnerEvent::class);
        Event::assertDispatched(ForgotCurrentOwnerEvent::class);
    });

    it('dispatches make and forget events for request scoped owner transitions', function (): void {
        Event::fake([
            MakingOwnerCurrentEvent::class,
            MadeOwnerCurrentEvent::class,
            ForgettingCurrentOwnerEvent::class,
            ForgotCurrentOwnerEvent::class,
        ]);

        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-evt-2';
            }
        };

        $originalRequest = app('request');
        $request = Request::create('/tenant', 'GET');
        app()->instance('request', $request);

        try {
            OwnerContext::setForRequest($owner);
            OwnerContext::setForRequest(null);
        } finally {
            app()->instance('request', $originalRequest);
        }

        Event::assertDispatched(MakingOwnerCurrentEvent::class);
        Event::assertDispatched(MadeOwnerCurrentEvent::class);
        Event::assertDispatched(ForgettingCurrentOwnerEvent::class);
        Event::assertDispatched(ForgotCurrentOwnerEvent::class);
    });

    it('does not dispatch duplicate make events when setForRequest receives the same owner again', function (): void {
        Event::fake([
            MakingOwnerCurrentEvent::class,
            MadeOwnerCurrentEvent::class,
            ForgettingCurrentOwnerEvent::class,
            ForgotCurrentOwnerEvent::class,
        ]);

        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-evt-same-request';
            }
        };

        $originalRequest = app('request');
        $request = Request::create('/tenant', 'GET');
        app()->instance('request', $request);

        try {
            OwnerContext::setForRequest($owner);
            OwnerContext::setForRequest($owner);
            OwnerContext::setForRequest(null);
        } finally {
            app()->instance('request', $originalRequest);
        }

        Event::assertDispatchedTimes(MakingOwnerCurrentEvent::class, 1);
        Event::assertDispatchedTimes(MadeOwnerCurrentEvent::class, 1);
        Event::assertDispatchedTimes(ForgettingCurrentOwnerEvent::class, 1);
        Event::assertDispatchedTimes(ForgotCurrentOwnerEvent::class, 1);
    });

    it('dispatches lifecycle events for each nested withOwner call even when owner is the same', function (): void {
        Event::fake([
            MakingOwnerCurrentEvent::class,
            MadeOwnerCurrentEvent::class,
            ForgettingCurrentOwnerEvent::class,
            ForgotCurrentOwnerEvent::class,
        ]);

        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-evt-same-nested';
            }
        };

        OwnerContext::withOwner($owner, function () use ($owner): void {
            expect((string) OwnerContext::resolve()?->getKey())->toBe((string) $owner->getKey());

            OwnerContext::withOwner($owner, function () use ($owner): void {
                expect((string) OwnerContext::resolve()?->getKey())->toBe((string) $owner->getKey());
            });

            expect((string) OwnerContext::resolve()?->getKey())->toBe((string) $owner->getKey());
        });

        Event::assertDispatchedTimes(MakingOwnerCurrentEvent::class, 2);
        Event::assertDispatchedTimes(MadeOwnerCurrentEvent::class, 2);
        Event::assertDispatchedTimes(ForgettingCurrentOwnerEvent::class, 2);
        Event::assertDispatchedTimes(ForgotCurrentOwnerEvent::class, 2);
    });
});
