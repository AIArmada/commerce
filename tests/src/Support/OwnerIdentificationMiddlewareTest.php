<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Middleware\OwnerIdentificationMiddleware;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

describe('OwnerIdentificationMiddleware', function (): void {
    beforeEach(function (): void {
        OwnerContext::withOwner(null, fn () => null);
    });

    it('identifies owner from request and sets context', function (): void {
        $owner = new class extends Model {
            public $timestamps = false;
            public function getMorphClass(): string { return 'store'; }
            public function getKey(): mixed { return 'store-123'; }
        };

        $middleware = new class($owner) extends OwnerIdentificationMiddleware {
            public function __construct(private Model $owner) {}

            protected function resolveOwnerFromRequest(Request $request)
            {
                return $this->owner;
            }
        };

        $request = Request::create('/dashboard', 'GET');
        $nextCalled = false;
        $contextInNext = null;

        $middleware->handle($request, function (Request $req) use (&$nextCalled, &$contextInNext) {
            $nextCalled = true;
            $contextInNext = OwnerContext::resolve();

            return response('OK');
        });

        expect($nextCalled)->toBeTrue()
            ->and($contextInNext)->not->toBeNull()
            ->and($contextInNext->getMorphClass())->toBe('store')
            ->and($contextInNext->getKey())->toBe('store-123');
    });

    it('sets null owner for global requests', function (): void {
        $middleware = new class extends OwnerIdentificationMiddleware {
            protected function resolveOwnerFromRequest(Request $request)
            {
                return null;
            }
        };

        $request = Request::create('/admin', 'GET');
        $contextInNext = 'NOT_SET';

        $middleware->handle($request, function (Request $req) use (&$contextInNext) {
            $contextInNext = OwnerContext::resolve();

            return response('OK');
        });

        expect($contextInNext)->toBeNull();
    });

    it('identifies owner from subdomain example', function (): void {
        $owner = new class extends Model {
            public $timestamps = false;
            public function getMorphClass(): string { return 'store'; }
            public function getKey(): mixed { return 'tenant-123'; }
        };

        $middleware = new class($owner) extends OwnerIdentificationMiddleware {
            public function __construct(private Model $owner) {}

            protected function resolveOwnerFromRequest(Request $request)
            {
                $subdomain = explode('.', $request->getHost())[0];

                if ($subdomain === 'app' || $subdomain === 'www') {
                    return null;
                }

                return $this->owner;
            }
        };

        $request = Request::create('/', 'GET', server: ['HTTP_HOST' => 'tenant1.example.test']);
        $contextInNext = null;

        $middleware->handle($request, function (Request $req) use (&$contextInNext) {
            $contextInNext = OwnerContext::resolve();

            return response('OK');
        });

        expect($contextInNext)->not->toBeNull()
            ->and($contextInNext->getMorphClass())->toBe('store');
    });

    it('identifies owner from auth context example', function (): void {
        $owner = new class extends Model {
            public $timestamps = false;
            public function getMorphClass(): string { return 'user'; }
            public function getKey(): mixed { return 'user-456'; }
        };

        $middleware = new class($owner) extends OwnerIdentificationMiddleware {
            public function __construct(private Model $owner) {}

            protected function resolveOwnerFromRequest(Request $request)
            {
                if ($request->user()) {
                    return $this->owner;
                }

                return null;
            }
        };

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(function () use ($owner) {
            return $owner;
        });

        $contextInNext = null;

        $middleware->handle($request, function (Request $req) use (&$contextInNext) {
            $contextInNext = OwnerContext::resolve();

            return response('OK');
        });

        expect($contextInNext)->not->toBeNull()
            ->and($contextInNext->getMorphClass())->toBe('user');
    });

    it('context cleanup is automatic (request attributes)', function (): void {
        $owner1 = new class extends Model {
            public $timestamps = false;
            public function getMorphClass(): string { return 'store'; }
            public function getKey(): mixed { return 'store-1'; }
        };

        $owner2 = new class extends Model {
            public $timestamps = false;
            public function getMorphClass(): string { return 'store'; }
            public function getKey(): mixed { return 'store-2'; }
        };

        $middleware = new class($owner1, $owner2) extends OwnerIdentificationMiddleware {
            public function __construct(private Model $owner1, private Model $owner2) {}

            protected function resolveOwnerFromRequest(Request $request)
            {
                return $request->query('owner') === '1' ? $this->owner1 : $this->owner2;
            }
        };

        // First request with owner1
        $request1 = Request::create('/test?owner=1', 'GET');
        $context1 = null;

        $middleware->handle($request1, function (Request $req) use (&$context1) {
            $context1 = OwnerContext::resolve();

            return response('OK');
        });

        expect($context1)->not->toBeNull()
            ->and($context1->getKey())->toBe('store-1');

        // Second request with owner2 (fresh request, fresh attributes)
        $request2 = Request::create('/test?owner=2', 'GET');
        $context2 = null;

        $middleware->handle($request2, function (Request $req) use (&$context2) {
            $context2 = OwnerContext::resolve();

            return response('OK');
        });

        expect($context2)->not->toBeNull()
            ->and($context2->getKey())->toBe('store-2');
    });
});
