<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Actions\ActivateCampaignAction;
use AIArmada\FilamentVouchers\Actions\ActivateGiftCardAction;
use AIArmada\FilamentVouchers\Actions\ActivateVoucherAction;
use AIArmada\FilamentVouchers\Actions\PauseCampaignAction;
use AIArmada\FilamentVouchers\Actions\PauseVoucherAction;
use AIArmada\FilamentVouchers\Actions\SuspendGiftCardAction;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\Models\Voucher;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('runs basic activate/pause actions without crashing', function (): void {

    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-actions@example.com',
        'password' => 'secret',
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'A-1',
        'name' => 'Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Paused,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucher->assignOwner($owner)->save();

    $campaign = Campaign::query()->create([
        'name' => 'Campaign',
        'slug' => 'campaign',
        'status' => CampaignStatus::Draft->value,
    ]);
    $campaign->assignOwner($owner)->save();

    $giftCard = GiftCard::query()->create([
        'code' => 'GC-1',
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'currency' => 'USD',
        'status' => GiftCardStatus::Inactive->value,
    ]);
    $giftCard->assignOwner($owner)->save();

    $activateVoucher = ActivateVoucherAction::make()->record($voucher);
    $pauseVoucher = PauseVoucherAction::make()->record($voucher);
    $activateCampaign = ActivateCampaignAction::make()->record($campaign);
    $pauseCampaign = PauseCampaignAction::make()->record($campaign);
    $activateGiftCard = ActivateGiftCardAction::make()->record($giftCard);
    $suspendGiftCard = SuspendGiftCardAction::make()->record($giftCard);

    expect($activateVoucher->isVisible())->toBeTrue();
    $handler = $activateVoucher->getActionFunction();
    $handler?->__invoke($voucher);
    $voucher->refresh();
    expect($voucher->status)->toBe(VoucherStatus::Active);

    expect($pauseVoucher->isVisible())->toBeTrue();
    $handler = $pauseVoucher->getActionFunction();
    $handler?->__invoke($voucher);
    $voucher->refresh();
    expect($voucher->status)->toBe(VoucherStatus::Paused);

    expect($activateCampaign->isVisible())->toBeTrue();
    $handler = $activateCampaign->getActionFunction();
    $handler?->__invoke($campaign);
    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Active);

    expect($pauseCampaign->isVisible())->toBeTrue();
    $handler = $pauseCampaign->getActionFunction();
    $handler?->__invoke($campaign);
    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Paused);

    expect($activateGiftCard->isVisible())->toBeTrue();
    $handler = $activateGiftCard->getActionFunction();
    $handler?->__invoke($giftCard);
    $giftCard->refresh();
    expect($giftCard->status)->toBe(GiftCardStatus::Active);

    expect($suspendGiftCard->isVisible())->toBeTrue();
    $handler = $suspendGiftCard->getActionFunction();
    $handler?->__invoke($giftCard);
    $giftCard->refresh();
    expect($giftCard->status)->toBe(GiftCardStatus::Suspended);
});
