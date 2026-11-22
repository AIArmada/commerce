<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\Resources\EventResource;

test('filament event resource can be subclassed with custom navigation and extension hooks', function (): void {
    $subclass = new class extends EventResource
    {
        protected static function getTableColumns(): array
        {
            return parent::getTableColumns();
        }

        protected static function getDefaultRelationManagers(): array
        {
            return parent::getDefaultRelationManagers();
        }
    };

    expect((new ReflectionClass(EventResource::class))->isFinal())->toBeFalse()
        ->and($subclass::getRelations())->not->toBeEmpty();
});
