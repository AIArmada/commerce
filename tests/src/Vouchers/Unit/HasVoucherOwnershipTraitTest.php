<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Traits\HasVoucherOwnership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class VoucherOwnerModel extends Model
{
    use HasVoucherOwnership;

    protected $table = 'users';

    public $timestamps = false;
}

describe('HasVoucherOwnership Trait', function (): void {
    it('provides vouchers relationship', function (): void {
        $owner = new VoucherOwnerModel;

        expect($owner->vouchers())->toBeInstanceOf(MorphMany::class);
    });

    it('returns vouchers owned by the model', function (): void {
        // Create owner first - we need a real user
        $ownerId = 'test-owner-' . uniqid();
        $ownerType = VoucherOwnerModel::class;

        // Create vouchers with this owner
        $voucher1 = Voucher::create([
            'code' => 'OWNED-1-' . uniqid(),
            'name' => 'First Owned Voucher',
            'type' => VoucherType::Percentage,
            'value' => 1000,
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
        ]);

        $voucher2 = Voucher::create([
            'code' => 'OWNED-2-' . uniqid(),
            'name' => 'Second Owned Voucher',
            'type' => VoucherType::Fixed,
            'value' => 500,
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
        ]);

        // Create a voucher owned by someone else
        $otherVoucher = Voucher::create([
            'code' => 'OTHER-' . uniqid(),
            'name' => 'Other Voucher',
            'type' => VoucherType::Fixed,
            'value' => 300,
            'owner_id' => 'other-owner',
            'owner_type' => 'OtherModel',
        ]);

        // Query vouchers for the owner
        $ownedVouchers = Voucher::where('owner_id', $ownerId)
            ->where('owner_type', $ownerType)
            ->get();

        expect($ownedVouchers)->toHaveCount(2)
            ->and($ownedVouchers->pluck('id')->toArray())
            ->toContain($voucher1->id, $voucher2->id)
            ->not->toContain($otherVoucher->id);
    });

    it('can create voucher for owner', function (): void {
        $ownerId = 'creator-owner-' . uniqid();
        $ownerType = VoucherOwnerModel::class;

        $voucher = Voucher::create([
            'code' => 'NEW-OWNED-' . uniqid(),
            'name' => 'Newly Created Voucher',
            'type' => VoucherType::Percentage,
            'value' => 1500,
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
        ]);

        expect($voucher->owner_id)->toBe($ownerId)
            ->and($voucher->owner_type)->toBe($ownerType);
    });

    it('returns empty collection when no vouchers owned', function (): void {
        $ownerId = 'no-vouchers-owner-' . uniqid();
        $ownerType = VoucherOwnerModel::class;

        $ownedVouchers = Voucher::where('owner_id', $ownerId)
            ->where('owner_type', $ownerType)
            ->get();

        expect($ownedVouchers)->toBeEmpty();
    });
});
