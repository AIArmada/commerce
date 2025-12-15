<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use Filament\Resources\Pages\EditRecord;
use ReflectionClass;
use ReflectionMethod;

describe('EditRole Page', function (): void {
    it('extends EditRecord', function (): void {
        expect(is_subclass_of(EditRole::class, EditRecord::class))->toBeTrue();
    });

    it('has correct resource', function (): void {
        $reflection = new ReflectionClass(EditRole::class);
        $property = $reflection->getProperty('resource');

        expect($property->getDefaultValue())->toBe(RoleResource::class);
    });

    it('has permissionIds property', function (): void {
        $reflection = new ReflectionClass(EditRole::class);
        $property = $reflection->getProperty('permissionIds');

        expect($property->isProtected())->toBeTrue();
        expect($property->getType()->getName())->toBe('array');
    });

    it('has getHeaderActions method', function (): void {
        $method = new ReflectionMethod(EditRole::class, 'getHeaderActions');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('array');
    });

    it('has mutateFormDataBeforeSave method', function (): void {
        $method = new ReflectionMethod(EditRole::class, 'mutateFormDataBeforeSave');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('array');
    });

    it('mutateFormDataBeforeSave extracts permissions from data', function (): void {
        $page = new class extends EditRole
        {
            public function testMutateFormDataBeforeSave(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }

            public function getPermissionIds(): array
            {
                return $this->permissionIds;
            }
        };

        $data = [
            'name' => 'admin',
            'permissions' => [1, 2, 3],
        ];

        $result = $page->testMutateFormDataBeforeSave($data);

        expect($result)->not->toHaveKey('permissions');
        expect($result)->toHaveKey('name');
        expect($page->getPermissionIds())->toBe(['1', '2', '3']);
    });

    it('mutateFormDataBeforeSave handles empty permissions', function (): void {
        $page = new class extends EditRole
        {
            public function testMutateFormDataBeforeSave(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }

            public function getPermissionIds(): array
            {
                return $this->permissionIds;
            }
        };

        $data = [
            'name' => 'user',
        ];

        $result = $page->testMutateFormDataBeforeSave($data);

        expect($result)->toBe(['name' => 'user']);
        expect($page->getPermissionIds())->toBe([]);
    });

    it('has afterSave method', function (): void {
        $method = new ReflectionMethod(EditRole::class, 'afterSave');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('void');
    });
});
