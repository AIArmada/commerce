<?php

declare(strict_types=1);

use AIArmada\Shipping\Features\Returns\Api\ManageReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('creates pending returns and ignores injected lifecycle and owner fields', function (): void {
    config(['shipping.features.owner.enabled' => false]);
    $api = app(ManageReturnAuthorization::class);
    $id = $api->create(['type' => 'refund', 'reason' => 'damaged', 'status' => 'completed', 'owner_id' => 'injected']);
    $record = ReturnAuthorization::query()->findOrFail($id);

    expect($record->isPending())->toBeTrue()
        ->and($record->owner_id)->toBeNull();
});

it('restricts item mutations to their owning return and validates quantities', function (): void {
    config(['shipping.features.owner.enabled' => false]);
    $api = app(ManageReturnAuthorization::class);
    $first = $api->create(['type' => 'refund', 'reason' => 'damaged']);
    $second = $api->create(['type' => 'refund', 'reason' => 'damaged']);
    $item = $api->createItem($first, ['name' => 'Item', 'quantity_requested' => 2]);

    expect(fn () => $api->updateItem($second, $item, ['quantity_requested' => 1]))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => $api->updateItem($first, $item, ['quantity_requested' => 0]))
        ->toThrow(DomainException::class);
});
