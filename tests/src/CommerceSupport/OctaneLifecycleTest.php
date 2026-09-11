<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Auditable;
use AIArmada\CommerceSupport\Contracts\Loggable;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\AuditableModelRegistry;
use AIArmada\CommerceSupport\Support\LoggableModelRegistry;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;

require_once __DIR__ . '/../../Support/OctaneEventShims.php';

it('flushes fallback owner context at a lifecycle boundary', function (): void {
    $owner = new class extends Model {};
    $originalRequest = app('request');

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    app()->offsetUnset('request');

    try {
        $fallback = new ReflectionProperty(OwnerContext::class, 'fallback');
        $fallback->setValue(null, ['hasOverride' => true, 'override' => $owner]);

        expect(OwnerContext::resolve())->toBe($owner);

        OwnerContext::flushState();

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and(OwnerContext::resolve())->toBeNull();
    } finally {
        app()->instance('request', $originalRequest);
        OwnerContext::flushState();
    }
});

it('flushes registered models from both lifecycle registries', function (): void {
    /** @var class-string<Auditable> $auditableModel */
    $auditableModel = Model::class;

    /** @var class-string<Loggable> $loggableModel */
    $loggableModel = Model::class;

    $auditableRegistry = new AuditableModelRegistry;
    $loggableRegistry = new LoggableModelRegistry;

    $auditableRegistry->register($auditableModel);
    $loggableRegistry->register($loggableModel);

    expect($auditableRegistry->getModels())->toBe([$auditableModel])
        ->and($loggableRegistry->getModels())->toBe([$loggableModel]);

    $auditableRegistry->flush();
    $loggableRegistry->flush();

    expect($auditableRegistry->getModels())->toBeEmpty()
        ->and($loggableRegistry->getModels())->toBeEmpty();
});

it('registers the support lifecycle flush without the filament authz provider', function (): void {
    $originalEvents = app('events');
    app()->instance('events', new Dispatcher(app()));

    $provider = new SupportServiceProvider(app());
    $provider->packageRegistered();

    $auditableRegistry = new AuditableModelRegistry;
    $loggableRegistry = new LoggableModelRegistry;
    app()->instance(AuditableModelRegistry::class, $auditableRegistry);
    app()->instance(LoggableModelRegistry::class, $loggableRegistry);

    /** @var class-string<Auditable> $auditableModel */
    $auditableModel = Model::class;

    /** @var class-string<Loggable> $loggableModel */
    $loggableModel = Model::class;

    $auditableRegistry->register($auditableModel);
    $loggableRegistry->register($loggableModel);

    $owner = new class extends Model {};
    $originalRequest = app('request');
    app()->offsetUnset('request');

    try {
        $fallback = new ReflectionProperty(OwnerContext::class, 'fallback');
        $fallback->setValue(null, ['hasOverride' => true, 'override' => $owner]);

        expect(OwnerContext::resolve())->toBe($owner)
            ->and($auditableRegistry->getModels())->not->toBeEmpty()
            ->and($loggableRegistry->getModels())->not->toBeEmpty();

        $receivedEvent = 'Laravel\\Octane\\Events\\RequestReceived';
        event(new $receivedEvent);

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and($auditableRegistry->getModels())->toBeEmpty()
            ->and($loggableRegistry->getModels())->toBeEmpty();

        $fallback->setValue(null, ['hasOverride' => true, 'override' => $owner]);
        $auditableRegistry->register($auditableModel);
        $loggableRegistry->register($loggableModel);

        $terminatedEvent = 'Laravel\\Octane\\Events\\RequestTerminated';
        event(new $terminatedEvent);

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and($auditableRegistry->getModels())->toBeEmpty()
            ->and($loggableRegistry->getModels())->toBeEmpty();
    } finally {
        app()->instance('request', $originalRequest);
        app()->instance('events', $originalEvents);
        OwnerContext::flushState();
    }
});

it('keeps support and filament authz lifecycle flushes idempotent', function (): void {
    $originalEvents = app('events');
    app()->instance('events', new Dispatcher(app()));

    $supportProvider = new SupportServiceProvider(app());
    $supportProvider->packageRegistered();

    $authzProvider = new FilamentAuthzServiceProvider(app());
    $authzMethod = new ReflectionMethod($authzProvider, 'registerOctaneListeners');
    $authzMethod->invoke($authzProvider);

    $auditableRegistry = new AuditableModelRegistry;
    $loggableRegistry = new LoggableModelRegistry;
    app()->instance(AuditableModelRegistry::class, $auditableRegistry);
    app()->instance(LoggableModelRegistry::class, $loggableRegistry);

    /** @var class-string<Auditable> $auditableModel */
    $auditableModel = Model::class;

    /** @var class-string<Loggable> $loggableModel */
    $loggableModel = Model::class;

    $auditableRegistry->register($auditableModel);
    $loggableRegistry->register($loggableModel);

    $originalRequest = app('request');
    $owner = new class extends Model {};

    try {
        $originalRequest->attributes->set(OwnerContext::REQUEST_KEY, [
            'hasOverride' => true,
            'override' => $owner,
        ]);

        $receivedEvent = 'Laravel\\Octane\\Events\\RequestReceived';
        event(new $receivedEvent);

        expect(OwnerContext::hasOverride())->toBeFalse()
            ->and($auditableRegistry->getModels())->toBeEmpty()
            ->and($loggableRegistry->getModels())->toBeEmpty();
    } finally {
        $originalRequest->attributes->remove(OwnerContext::REQUEST_KEY);
        app()->instance('request', $originalRequest);
        app()->instance('events', $originalEvents);
        OwnerContext::flushState();
    }
});
