<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Data\PayoutStatusResult;
use AIArmada\Affiliates\Drivers\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Payouts\States\FailedPayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Actions\BulkPayoutAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Permission::firstOrCreate([
        'name' => 'affiliates.payout.update',
        'guard_name' => 'web',
    ]);

    AffiliatePayoutEvent::query()->delete();
    AffiliatePayoutMethod::query()->delete();
    AffiliatePayoutOperation::query()->delete();
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
});

/** @param array<string, mixed> $attributes */
function createBulkActionPayout(array $attributes): AffiliatePayout
{
    $payout = AffiliatePayout::create($attributes);
    $affiliate = $payout->payee;

    if (! $affiliate instanceof Affiliate) {
        throw new RuntimeException('Canonical payout fixture requires an affiliate payee.');
    }

    $operation = AffiliatePayoutOperation::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_payout_id' => $payout->getKey(),
        'operation_key' => 'test:' . $payout->getKey(),
        'status' => 'reserved',
        'amount_minor' => $payout->total_minor,
        'currency' => $payout->currency,
        'claimed_at' => now(),
        'owner_type' => $payout->owner_type,
        'owner_id' => $payout->owner_id,
    ]);

    $payout->forceFill(['affiliate_payout_operation_id' => $operation->getKey()])->save();

    return $payout;
}

it('processes a pending payout successfully', function (): void {
    $user = User::create([
        'name' => 'Payout User',
        'email' => 'payout-user@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = Affiliate::create([
        'code' => 'PAYOUT-' . Str::uuid(),
        'name' => 'Payout Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $payout = createBulkActionPayout([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestSuccessPayoutProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(CompletedPayout::class)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->external_reference)->toBe('EXT-123');

    $event = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'Provider outcome: completed')->first();
    expect($event)->not->toBeNull()
        ->and($event->from_status)->toBe(ProcessingPayout::value())
        ->and($event->to_status)->toBe(CompletedPayout::value())
        ->and($event->notes)->toBe('Provider outcome: completed');
});

it('marks a pending payout as failed when no default payout method exists', function (): void {
    $user = User::create([
        'name' => 'No Method User',
        'email' => 'no-method@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = Affiliate::create([
        'code' => 'NOMETHOD-' . Str::uuid(),
        'name' => 'No Method Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $payout = createBulkActionPayout([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(FailedPayout::class);

    $event = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'No default payout method is configured.')->first();
    expect($event)->not->toBeNull()
        ->and($event->to_status)->toBe(FailedPayout::value())
        ->and($event->notes)->toBe('No default payout method is configured.');
});

it('marks a pending payout as failed when the processor fails', function (): void {
    $user = User::create([
        'name' => 'Fail User',
        'email' => 'fail-user@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = Affiliate::create([
        'code' => 'FAIL-' . Str::uuid(),
        'name' => 'Fail Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $payout = createBulkActionPayout([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestFailingPayoutProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(FailedPayout::class);

    $event = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'Provider outcome: failed')->first();
    expect($event)->not->toBeNull()
        ->and($event->to_status)->toBe(FailedPayout::value())
        ->and($event->notes)->toBe('Provider outcome: failed');
});

it('leaves a payout processing when the processor throws', function (): void {
    $user = User::create([
        'name' => 'Throw User',
        'email' => 'throw-user@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = Affiliate::create([
        'code' => 'THROW-' . Str::uuid(),
        'name' => 'Throw Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
        'verified_at' => now(),
        'is_default' => true,
    ]);

    $payout = createBulkActionPayout([
        'reference' => 'PAYOUT-REF-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 1500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestThrowingPayoutProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    $payout->refresh();

    expect($payout->status)->toBeInstanceOf(ProcessingPayout::class)
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->status)->toBe('unknown')
        ->and(AffiliatePayoutOperation::query()->findOrFail($payout->affiliate_payout_operation_id)->last_error_code)->toBe('PAYOUT_PROCESSING_EXCEPTION');

    $event = AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payout->getKey())->where('notes', 'Provider outcome: unknown (PAYOUT_PROCESSING_EXCEPTION)')->first();
    expect($event)->not->toBeNull()
        ->and($event->from_status)->toBe(ProcessingPayout::value())
        ->and($event->to_status)->toBe(ProcessingPayout::value())
        ->and($event->notes)->toBe('Provider outcome: unknown (PAYOUT_PROCESSING_EXCEPTION)');
});

it('rejects cross-tenant payout selection without mutating any selected payouts', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a-payout@example.com',
        'password' => 'secret',
    ]);
    $ownerA->givePermissionTo('affiliates.payout.update');

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b-payout@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($ownerA);

    $payoutA = OwnerContext::withOwner($ownerA, function (): AffiliatePayout {
        $affiliate = Affiliate::create([
            'code' => 'A-' . Str::uuid(),
            'name' => 'Affiliate A',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        AffiliatePayoutMethod::create([
            'affiliate_id' => $affiliate->getKey(),
            'type' => PayoutMethodType::BankTransfer,
            'details' => ['bank_name' => 'Test Bank', 'account_number' => '123456789'],
            'verified_at' => now(),
            'is_default' => true,
        ]);

        $payout = AffiliatePayout::create([
            'reference' => 'PAY-A-' . Str::uuid(),
            'status' => PendingPayout::class,
            'total_minor' => 1500,
            'currency' => 'USD',
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        return $payout;
    });

    $payoutB = OwnerContext::withOwner($ownerB, function (): AffiliatePayout {
        $affiliate = Affiliate::create([
            'code' => 'B-' . Str::uuid(),
            'name' => 'Affiliate B',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'reference' => 'PAY-B-' . Str::uuid(),
            'status' => PendingPayout::class,
            'total_minor' => 1500,
            'currency' => 'USD',
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        return $payout;
    });

    $factory = new PayoutProcessorFactory;
    $factory->register('bank_transfer', TestSuccessPayoutProcessor::class);
    app()->instance(PayoutProcessorRegistry::class, $factory);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);

    OwnerContext::withOwner($ownerA, function () use ($action, $payoutA, $payoutB): void {
        expect(fn () => $action->call(['records' => new Collection([$payoutA, $payoutB])]))
            ->toThrow(AuthorizationException::class);
    });

    $payoutA->refresh();
    $payoutB->refresh();

    expect($payoutA->status)->toBeInstanceOf(PendingPayout::class)
        ->and($payoutB->status)->toBeInstanceOf(PendingPayout::class)
        ->and(AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payoutA->getKey())->count())->toBe(0)
        ->and(AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payoutB->getKey())->count())->toBe(0);
});

class TestSuccessPayoutProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        return PayoutResult::success('EXT-123', ['processor' => 'test']);
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('completed');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-success';
    }
}

class TestFailingPayoutProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        return PayoutResult::failure('Processor failed');
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('failed');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-failure';
    }
}

class TestThrowingPayoutProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        throw new Exception('Processor exploded');
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult('failed');
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return null;
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'test-throwing';
    }
}
