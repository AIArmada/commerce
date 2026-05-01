<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

if (! function_exists('filamentCustomers_makeOwner')) {
    function filamentCustomers_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:owner';
            }
        };
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('syncManualCustomers rejects syncing when the segment record is outside current owner scope', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $customerInScope = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Inside',
        'last_name' => 'Scope',
        'email' => 'inside-scope-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $segmentOutsideScope = OwnerContext::withOwner($ownerB, fn (): Segment => Segment::query()->create([
        'name' => 'Outside Segment',
        'slug' => 'outside-segment-' . uniqid(),
        'type' => 'custom',
        'is_automatic' => false,
        'is_active' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => SegmentResource::syncManualCustomers($segmentOutsideScope, [
        $customerInScope->id,
    ])))->toThrow(HttpException::class);
});
