<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\EditPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ListPermissionRequests;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ViewPermissionRequest;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use ReflectionClass;
use ReflectionMethod;

describe('PermissionRequestResource Pages', function (): void {
    describe('ListPermissionRequests', function (): void {
        it('extends ListRecords', function (): void {
            expect(is_subclass_of(ListPermissionRequests::class, ListRecords::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(ListPermissionRequests::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(PermissionRequestResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new ReflectionMethod(ListPermissionRequests::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });

    describe('EditPermissionRequest', function (): void {
        it('extends EditRecord', function (): void {
            expect(is_subclass_of(EditPermissionRequest::class, EditRecord::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(EditPermissionRequest::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(PermissionRequestResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new ReflectionMethod(EditPermissionRequest::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });

    describe('ViewPermissionRequest', function (): void {
        it('extends ViewRecord', function (): void {
            expect(is_subclass_of(ViewPermissionRequest::class, ViewRecord::class))->toBeTrue();
        });

        it('has correct resource', function (): void {
            $reflection = new ReflectionClass(ViewPermissionRequest::class);
            $property = $reflection->getProperty('resource');

            expect($property->getDefaultValue())->toBe(PermissionRequestResource::class);
        });

        it('has getHeaderActions method', function (): void {
            $method = new ReflectionMethod(ViewPermissionRequest::class, 'getHeaderActions');

            expect($method->isProtected())->toBeTrue();
            expect($method->getReturnType()->getName())->toBe('array');
        });
    });
});
