<?php

declare(strict_types=1);

use AIArmada\FilamentEvents\RelationManagers\EventsRelationManager;
use AIArmada\FilamentEvents\Resources\EventResource;
use Filament\Resources\RelationManagers\RelationManager;

it('embeds the event resource table on any events relationship', function (): void {
    $relationship = new ReflectionProperty(EventsRelationManager::class, 'relationship');
    $title = new ReflectionProperty(EventsRelationManager::class, 'title');
    $relatedResource = new ReflectionProperty(EventsRelationManager::class, 'relatedResource');

    expect(is_subclass_of(EventsRelationManager::class, RelationManager::class))->toBeTrue()
        ->and($relationship->getValue())->toBe('events')
        ->and($title->getValue())->toBe('Events')
        ->and($relatedResource->getValue())->toBe(EventResource::class);
});
