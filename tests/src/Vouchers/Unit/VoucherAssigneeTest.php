<?php

declare(strict_types=1);

// Generic recipient-assignee seam: a voucher carrying
// metadata['assignee'] = ['type', 'id'] refuses every other account at
// validation and usage recording. Unmarked vouchers behave exactly as
// before. Usage recording also refuses expired/revoked vouchers, so an
// unused revocation cannot be followed by a late use.

use AIArmada\Commerce\Tests\Fixtures\Models\User as FixtureUser;
use AIArmada\Vouchers\Actions\CreateVoucher;
use AIArmada\Vouchers\Actions\ExpireVoucher;
use AIArmada\Vouchers\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Actions\ValidateVoucherCode;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\VoucherExpiredException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Active;
use Akaunting\Money\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function assigneeUser(): FixtureUser
{
    $user = new FixtureUser(['name' => 'Assignee', 'email' => 'assignee-' . Str::random(8) . '@example.test']);
    $user->forceFill(['id' => (string) Str::uuid()]);

    return $user;
}

function assigneeVoucher(FixtureUser $assignee, array $attributes = []): Voucher
{
    return CreateVoucher::run(array_merge([
        'code' => 'ASSIGN-' . mb_strtoupper(Str::random(8)),
        'name' => 'Assigned voucher',
        'type' => VoucherType::Fixed,
        'value' => 2000,
        'currency' => 'MYR',
        'usage_limit' => 1,
        'usage_limit_per_user' => 1,
        'metadata' => ['assignee' => ['type' => $assignee->getMorphClass(), 'id' => (string) $assignee->getKey()]],
    ], $attributes));
}

it('validates an assigned voucher only for its assignee', function (): void {
    $assignee = assigneeUser();
    $stranger = assigneeUser();
    $voucher = assigneeVoucher($assignee);

    Auth::setUser($assignee);
    expect(ValidateVoucherCode::run($voucher->code, null)->isValid)->toBeTrue();

    Auth::setUser($stranger);
    config()->set('vouchers.validation.check_assignee', false);
    $foreign = ValidateVoucherCode::run($voucher->code, null);
    expect($foreign->isValid)->toBeFalse()
        ->and($foreign->reason)->toContain('another account');

    Auth::logout();
    $guest = ValidateVoucherCode::run($voucher->code, null);
    expect($guest->isValid)->toBeFalse()
        ->and($guest->reason)->toContain('another account');
});

it('rejects present but malformed assignee markers', function (mixed $assignee): void {
    $voucher = Voucher::create([
        'code' => 'BAD-ASSIGNEE-' . mb_strtoupper(Str::random(8)),
        'name' => 'Invalid assignment',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
        'metadata' => ['assignee' => $assignee],
    ]);

    Auth::setUser(assigneeUser());

    $validation = ValidateVoucherCode::run($voucher->code, null);

    expect($validation->isValid)->toBeFalse()
        ->and($validation->reason)->toContain('invalid recipient assignment');

    expect(fn () => RecordVoucherUsage::run($voucher->code, Money::MYR(100)))
        ->toThrow(InvalidVoucherException::class, 'invalid recipient assignment');
})->with([
    'null marker' => [null],
    'string marker' => ['user:1'],
    'missing id' => [['type' => 'users']],
    'empty type' => [['type' => '', 'id' => '1']],
    'empty id' => [['type' => 'users', 'id' => '']],
]);

it('leaves unmarked commercial vouchers untouched by the assignee check', function (): void {
    $user = assigneeUser();

    $voucher = Voucher::create([
        'code' => 'OPEN-' . mb_strtoupper(Str::random(8)),
        'name' => 'Open voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    Auth::setUser($user);
    expect(ValidateVoucherCode::run($voucher->code, null)->isValid)->toBeTrue();

    Auth::logout();
    expect(ValidateVoucherCode::run($voucher->code, null)->isValid)->toBeTrue();

    // Unattributed usage on an unmarked voucher still records.
    $usage = RecordVoucherUsage::run($voucher->code, Money::MYR(100), metadata: ['idempotency_key' => 'open-1']);

    expect($usage->redeemed_by_id)->toBeNull();
});

it('records usage on assigned vouchers only for the assignee', function (): void {
    $assignee = assigneeUser();
    $stranger = assigneeUser();
    $voucher = assigneeVoucher($assignee);

    expect(fn () => RecordVoucherUsage::run(
        $voucher->code,
        Money::MYR(100),
        metadata: ['idempotency_key' => 'forbid-1'],
        redeemedBy: $stranger,
    ))->toThrow(InvalidVoucherException::class, 'another account');

    expect(fn () => RecordVoucherUsage::run(
        $voucher->code,
        Money::MYR(100),
        metadata: ['idempotency_key' => 'forbid-2'],
    ))->toThrow(InvalidVoucherException::class, 'another account');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(0);

    $usage = RecordVoucherUsage::run(
        $voucher->code,
        Money::MYR(100),
        metadata: ['idempotency_key' => 'allow-1'],
        redeemedBy: $assignee,
    );

    expect((string) $usage->redeemed_by_id)->toBe((string) $assignee->getKey());
});

it('refuses usage on expired and revoked vouchers', function (): void {
    $pastDue = Voucher::create([
        'code' => 'PAST-' . mb_strtoupper(Str::random(8)),
        'name' => 'Past due',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
        'expires_at' => now()->subDay(),
    ]);

    expect(fn () => RecordVoucherUsage::run(
        $pastDue->code,
        Money::MYR(100),
        metadata: ['idempotency_key' => 'past-1'],
    ))->toThrow(VoucherExpiredException::class);

    $revoked = Voucher::create([
        'code' => 'REVOKED-' . mb_strtoupper(Str::random(8)),
        'name' => 'Revoked',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);

    ExpireVoucher::run($revoked->code);

    expect(fn () => RecordVoucherUsage::run(
        $revoked->code,
        Money::MYR(100),
        metadata: ['idempotency_key' => 'revoked-1'],
    ))->toThrow(VoucherExpiredException::class);

    expect(VoucherUsage::count())->toBe(0);
});

it('replays an existing idempotent usage after the voucher expires', function (): void {
    $voucher = Voucher::create([
        'code' => 'REPLAY-' . mb_strtoupper(Str::random(8)),
        'name' => 'Replay after expiry',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);
    $metadata = ['idempotency_key' => 'replay-after-expiry'];

    $firstUsage = RecordVoucherUsage::run($voucher->code, Money::MYR(100), metadata: $metadata);

    $voucher->forceFill(['expires_at' => now()->subDay()])->save();

    $replayedUsage = RecordVoucherUsage::run($voucher->code, Money::MYR(100), metadata: $metadata);

    expect($replayedUsage->id)->toBe($firstUsage->id)
        ->and(VoucherUsage::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('rejects an idempotency key replayed for a different redeemer', function (): void {
    $voucher = Voucher::create([
        'code' => 'IDENTITY-' . mb_strtoupper(Str::random(8)),
        'name' => 'Idempotency identity',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'MYR',
        'status' => Active::class,
    ]);
    $firstRedeemer = assigneeUser();
    $secondRedeemer = assigneeUser();
    $metadata = ['idempotency_key' => 'same-redemption-key'];

    RecordVoucherUsage::run($voucher->code, Money::MYR(100), metadata: $metadata, redeemedBy: $firstRedeemer);

    expect(fn () => RecordVoucherUsage::run(
        $voucher->code,
        Money::MYR(100),
        metadata: $metadata,
        redeemedBy: $secondRedeemer,
    ))->toThrow(ValidationException::class);
});
