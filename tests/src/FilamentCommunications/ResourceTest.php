<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Filament\Communications\Resources\CommunicationBatchResource;
use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource;
use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource;
use AIArmada\Filament\Communications\Resources\CommunicationResource;
use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource;
use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource;
use AIArmada\Filament\Communications\Resources\CommunicationThreadResource;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

$resources = [
    CommunicationResource::class,
    CommunicationDeliveryResource::class,
    CommunicationThreadResource::class,
    CommunicationTemplateResource::class,
    CommunicationPreferenceResource::class,
    CommunicationSuppressionResource::class,
    CommunicationBatchResource::class,
];

describe('navigation configuration', function () use ($resources): void {
    beforeEach(function (): void {
        config()->set('filament-communications.navigation.group', 'Test Communications');
        config()->set('filament-communications.navigation.sort', 80);
    });

    test('getNavigationGroup returns config value for all resources', function (string $resourceClass): void {
        $group = $resourceClass::getNavigationGroup();
        /** @phpstan-ignore argument.templateType */
        expect($group)->toBe('Test Communications');
    })->with($resources);

    test('getNavigationSort returns config value for all resources', function (string $resourceClass): void {
        $sort = $resourceClass::getNavigationSort();
        /** @phpstan-ignore argument.templateType */
        expect($sort)->toBe(80);
    })->with($resources);

});

describe('resource methods', function () use ($resources): void {
    test('getEloquentQuery returns a builder for all resources', function (string $resourceClass): void {
        $query = OwnerContext::withOwner(null, fn () => $resourceClass::getEloquentQuery());

        /** @phpstan-ignore argument.templateType */
        expect($query)->toBeInstanceOf(EloquentBuilder::class);
    })->with($resources);

    test('getPages returns array with index and view for all resources', function (string $resourceClass): void {
        $pages = $resourceClass::getPages();

        /** @phpstan-ignore argument.templateType */
        expect($pages)->toBeArray();
        /** @phpstan-ignore argument.templateType */
        expect($pages)->toHaveKey('index');
        /** @phpstan-ignore argument.templateType */
        expect($pages)->toHaveKey('view');
    })->with($resources);
});

test('no resource declares static $navigationGroup', function () use ($resources): void {
    foreach ($resources as $resourceClass) {
        $reflection = new ReflectionClass($resourceClass);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            if ($property->getName() === 'navigationGroup') {
                expect($property->getDeclaringClass()->getName())->not->toBe($resourceClass);
            }
        }
    }
});

test('delivery resource exposes a retry action', function (): void {
    $table = CommunicationDeliveryResource::table(Table::make(Mockery::mock(HasTable::class)));
    $actions = array_map(
        static fn ($action): string => $action->getName(),
        $table->getActions(),
    );

    expect($actions)->toContain('retry', 'view');
});
