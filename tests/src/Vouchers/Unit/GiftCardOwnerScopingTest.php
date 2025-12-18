<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

class GiftCardTestOwner extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'gift_card_test_owners';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::dropIfExists('gift_card_test_owners');

    Schema::create('gift_card_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
    });

    config([
        'vouchers.owner.enabled' => true,
        'vouchers.owner.include_global' => true,
        'vouchers.owner.auto_assign_on_create' => true,
    ]);
});

it('auto-assigns gift cards to the resolved owner on create', function (): void {
    $owner = GiftCardTestOwner::create(['name' => 'Merchant A']);

    app()->forgetInstance(OwnerResolverInterface::class);
    app()->singleton(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface {
        public function __construct(private GiftCardTestOwner $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $service = new GiftCardService;

    $giftCard = $service->issue([
        'initial_balance' => 10000,
        'type' => GiftCardType::Standard,
        'status' => GiftCardStatus::Inactive,
    ]);

    expect($giftCard->owner_type)->toBe($owner->getMorphClass())
        ->and($giftCard->owner_id)->toBe($owner->getKey());
});

it('blocks cross-tenant gift card reads and actions', function (): void {
    $ownerA = GiftCardTestOwner::create(['name' => 'Merchant A']);
    $ownerB = GiftCardTestOwner::create(['name' => 'Merchant B']);

    app()->forgetInstance(OwnerResolverInterface::class);
    app()->singleton(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface {
        public function __construct(private GiftCardTestOwner $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $service = new GiftCardService;

    $cardA = $service->issue(['initial_balance' => 10000]);

    $cardB = GiftCard::create([
        'code' => 'GC-OTHER-0001',
        'type' => GiftCardType::Standard,
        'currency' => 'MYR',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'status' => GiftCardStatus::Inactive,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    expect($service->findByCode($cardA->code))->not->toBeNull()
        ->and($service->findByCode($cardB->code))->toBeNull()
        ->and(GiftCard::findByCode($cardB->code))->toBeNull();

    $service->activate($cardA->code);

    expect(fn () => $service->activate($cardB->code))
        ->toThrow(RuntimeException::class);
});
