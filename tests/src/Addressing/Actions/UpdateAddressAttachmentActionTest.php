<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAttachmentWriter;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Traits\HasAddresses;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

final class UpdateAttachmentTestOwner extends Model
{
    use HasAddresses;

    protected $table = 'test_owners';
}

beforeEach(function (): void {
    $this->host = UpdateAttachmentTestOwner::query()->create();
    $this->address = Address::query()->create(['line1' => '8 Market Street', 'country_code' => 'MY']);
});

it('updates one attachment type and preserves its identity and metadata', function (): void {
    $pivot = $this->host->attachAddress($this->address, type: 'delivery', label: 'Front desk');
    $validFrom = CarbonImmutable::now()->subDay();
    $pivot->valid_from = $validFrom;
    $pivot->metadata = ['source' => 'import'];
    $pivot->save();
    $id = $pivot->getKey();

    $updated = Addressable::query()->findOrFail(app(AddressAttachmentWriter::class)->execute((string) $pivot->getKey(), [
        'type' => 'shipping',
        'is_primary' => true,
    ]));

    expect($updated->getKey())->toBe($id)
        ->and($updated->type)->toBe('shipping')
        ->and($updated->label)->toBe('Front desk')
        ->and($updated->valid_from->format('Y-m-d H:i:s'))->toBe($validFrom->format('Y-m-d H:i:s'))
        ->and($updated->metadata)->toBe(['source' => 'import'])
        ->and($updated->is_primary)->toBeTrue();
});

it('clears an attachment label only when null is explicitly submitted', function (): void {
    $pivot = $this->host->attachAddress($this->address, type: 'shipping', label: 'Front desk');
    $action = app(AddressAttachmentWriter::class);

    $preserved = Addressable::query()->findOrFail($action->execute((string) $pivot->getKey(), ['type' => 'shipping', 'is_primary' => false]));
    $cleared = Addressable::query()->findOrFail($action->execute((string) $preserved->getKey(), ['type' => 'shipping', 'label' => null, 'is_primary' => false]));

    expect($preserved->label)->toBe('Front desk')
        ->and($cleared->label)->toBeNull();
});

it('rolls back primary demotion when an attachment save is cancelled', function (): void {
    $primary = $this->host->attachAddress($this->address, type: 'shipping', isPrimary: true);
    $otherAddress = Address::query()->create(['line1' => '9 Market Street', 'country_code' => 'MY']);
    $other = $this->host->attachAddress($otherAddress, type: 'billing', isPrimary: true);
    $dispatcher = Event::getFacadeRoot();
    $event = 'eloquent.saving: ' . Addressable::class;
    $dispatcher->listen($event, static fn (): bool => false);

    try {
        expect(fn () => app(AddressAttachmentWriter::class)->execute((string) $other->getKey(), [
            'type' => 'shipping',
            'is_primary' => true,
        ]))->toThrow(RuntimeException::class, 'could not be updated');
    } finally {
        $dispatcher->forget($event);
    }

    expect($primary->fresh()->is_primary)->toBeTrue()
        ->and($other->fresh()->type)->toBe('billing')
        ->and($other->fresh()->is_primary)->toBeTrue();
});

it('rejects a duplicate address attachment type without changing the existing pivot', function (): void {
    $first = $this->host->attachAddress($this->address, type: 'shipping');
    $second = $this->host->attachAddress($this->address, type: 'billing');

    expect(fn (): string => app(AddressAttachmentWriter::class)->execute((string) $second->getKey(), [
        'type' => 'shipping',
        'is_primary' => false,
    ]))->toThrow(ValidationException::class);

    expect($first->fresh()->type)->toBe('shipping')
        ->and($second->fresh()->type)->toBe('billing');
});

it('detaches only the selected pivot when an address has multiple attachment types', function (): void {
    $shipping = $this->host->attachAddress($this->address, type: 'shipping');
    $billing = $this->host->attachAddress($this->address, type: 'billing');

    app(AddressAttachmentWriter::class)->detach((string) $shipping->getKey());

    expect(Addressable::query()->whereKey($shipping->getKey())->exists())->toBeFalse()
        ->and(Addressable::query()->whereKey($billing->getKey())->exists())->toBeTrue();
});

it('does not report a cancelled attachment deletion as successful', function (): void {
    $pivot = $this->host->attachAddress($this->address, type: 'shipping');
    $dispatcher = Event::getFacadeRoot();
    $event = 'eloquent.deleting: ' . Addressable::class;
    $dispatcher->listen($event, static fn (): bool => false);

    try {
        expect(fn () => app(AddressAttachmentWriter::class)->detach((string) $pivot->getKey()))
            ->toThrow(RuntimeException::class, 'could not be removed');
    } finally {
        $dispatcher->forget($event);
    }

    expect(Addressable::query()->whereKey($pivot->getKey())->exists())->toBeTrue();
});

it('selects a different primary attachment without changing other types', function (): void {
    $first = $this->host->attachAddress($this->address, type: 'shipping', isPrimary: true);
    $otherAddress = Address::query()->create(['line1' => '9 Market Street', 'country_code' => 'MY']);
    $second = $this->host->attachAddress($otherAddress, type: 'shipping');
    $billing = $this->host->attachAddress($this->address, type: 'billing', isPrimary: true);

    $selected = $this->host->setPrimaryAddress($otherAddress, 'shipping');

    expect($selected->getKey())->toBe($second->getKey())
        ->and($selected->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($billing->fresh()->is_primary)->toBeTrue();
});

it('rolls back demotion when an attachment creation is vetoed', function (): void {
    $first = $this->host->attachAddress($this->address, type: 'shipping', isPrimary: true);
    $otherAddress = Address::query()->create(['line1' => '9 Market Street', 'country_code' => 'MY']);
    $dispatcher = Event::getFacadeRoot();
    $event = 'eloquent.creating: ' . Addressable::class;
    $dispatcher->listen($event, static fn (): bool => false);

    try {
        expect(fn () => $this->host->attachAddress($otherAddress, type: 'shipping', isPrimary: true))
            ->toThrow(RuntimeException::class, 'could not be created');
    } finally {
        $dispatcher->forget($event);
    }

    expect($first->fresh()->is_primary)->toBeTrue()
        ->and($this->host->addressAttachments()->count())->toBe(1);
});
