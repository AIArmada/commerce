<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\EditPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ListPermissionRequests;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ViewPermissionRequest;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

        it('returns create header action', function (): void {
            $page = new ListPermissionRequests();

            $method = new ReflectionMethod(ListPermissionRequests::class, 'getHeaderActions');
            $method->setAccessible(true);

            /** @var array<int, Action> $actions */
            $actions = $method->invoke($page);

            expect($actions)->toHaveCount(1)
                ->and($actions[0])->toBeInstanceOf(CreateAction::class);
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

        it('returns view and delete header actions', function (): void {
            $page = new EditPermissionRequest();

            $method = new ReflectionMethod(EditPermissionRequest::class, 'getHeaderActions');
            $method->setAccessible(true);

            /** @var array<int, Action> $actions */
            $actions = $method->invoke($page);

            expect($actions)->toHaveCount(2)
                ->and($actions[0])->toBeInstanceOf(ViewAction::class)
                ->and($actions[1])->toBeInstanceOf(DeleteAction::class);
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

        it('returns edit header action', function (): void {
            $page = new ViewPermissionRequest();

            $method = new ReflectionMethod(ViewPermissionRequest::class, 'getHeaderActions');
            $method->setAccessible(true);

            /** @var array<int, Action> $actions */
            $actions = $method->invoke($page);

            expect($actions)->toHaveCount(1)
                ->and($actions[0])->toBeInstanceOf(EditAction::class);
        });
    });
});
