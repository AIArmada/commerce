<?php

declare(strict_types=1);

use AIArmada\Cart\Testing\InMemoryStorage;

describe('InMemoryStorage', function (): void {
    it('can store and retrieve items', function (): void {
        $storage = new InMemoryStorage();

        $items = [['id' => 'item1', 'name' => 'Item 1']];
        $storage->putItems('identifier', 'instance', $items);

        expect($storage->getItems('identifier', 'instance'))->toBe($items);
    });

    it('can check if cart exists', function (): void {
        $storage = new InMemoryStorage();

        expect($storage->has('identifier', 'instance'))->toBeFalse();

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);

        expect($storage->has('identifier', 'instance'))->toBeTrue();
    });

    it('stores conditions and metadata and clears them', function (): void {
        $storage = new InMemoryStorage();

        $storage->putConditions('identifier', 'instance', [['name' => 'cond']]);
        $storage->putMetadata('identifier', 'instance', 'key', 'value');

        expect($storage->getConditions('identifier', 'instance'))->toBe([['name' => 'cond']]);
        expect($storage->getMetadata('identifier', 'instance', 'key'))->toBe('value');
        expect($storage->getAllMetadata('identifier', 'instance'))
            ->toEqual(['key' => 'value']);

        $storage->clearMetadata('identifier', 'instance');
        expect($storage->getAllMetadata('identifier', 'instance'))->toBe([]);
    });

    it('supports clearAll and forget operations', function (): void {
        $storage = new InMemoryStorage();

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);
        $storage->putConditions('identifier', 'instance', [['name' => 'cond']]);
        $storage->putMetadata('identifier', 'instance', 'key', 'value');

        $storage->clearAll('identifier', 'instance');

        expect($storage->getItems('identifier', 'instance'))->toBe([]);
        expect($storage->getConditions('identifier', 'instance'))->toBe([]);
        expect($storage->getAllMetadata('identifier', 'instance'))->toBe([]);

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);
        $storage->forget('identifier', 'instance');

        expect($storage->has('identifier', 'instance'))->toBeFalse();
    });

    it('supports instances, versions, ids and identifier swap', function (): void {
        $storage = new InMemoryStorage();

        $storage->putItems('id-1', 'inst-1', [['id' => 'item1']]);
        $storage->putItems('id-1', 'inst-2', [['id' => 'item2']]);

        // Instances are tracked
        expect($storage->getInstances('id-1'))->toHaveCount(2);

        // Version increments with writes
        expect($storage->getVersion('id-1', 'inst-3'))->toBeNull();
        $storage->putItems('id-1', 'inst-3', [['id' => 'item1']]);
        expect($storage->getVersion('id-1', 'inst-3'))->toBe(1);
        $storage->putConditions('id-1', 'inst-3', [['name' => 'cond']]);
        expect($storage->getVersion('id-1', 'inst-3'))->toBe(2);

        // ID is lazily generated and stable
        $id = $storage->getId('id-1', 'inst-1');
        expect($id)->toBeString();
        expect($storage->getId('id-1', 'inst-1'))->toBe($id);

        // Swap identifier moves all data
        $storage->putMetadata('id-1', 'inst-1', 'k', 'v');
        $result = $storage->swapIdentifier('id-1', 'id-2', 'inst-1');

        expect($result)->toBeTrue();
        expect($storage->getItems('id-2', 'inst-1'))->not->toBe([]);
        expect($storage->getAllMetadata('id-2', 'inst-1'))->toHaveKey('k');
    });
});
