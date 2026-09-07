<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Auditable;
use AIArmada\CommerceSupport\Contracts\Loggable;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\AuditableModelRegistry;
use AIArmada\CommerceSupport\Support\LoggableModelRegistry;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;

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
