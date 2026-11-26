<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Authz\Features\Authorization\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateTaxDocumentResource;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Support\AffiliateReferenceProjection;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();

});

it('applies explicit owner filters to affiliate resources that list tenant-owned data', function (): void {
    $owner = User::create([
        'name' => 'Resource Owner',
        'email' => 'affiliate-resource-owner@example.com',
        'password' => 'secret',
    ]);

    $resources = [
        AffiliateConversionResource::class,
        AffiliateCreativeResource::class,
        AffiliateLinkResource::class,
        AffiliateRankHistoryResource::class,
        AffiliateSupportTicketResource::class,
        AffiliateTaxDocumentResource::class,
    ];

    OwnerContext::withOwner($owner, function () use ($resources): void {
        foreach ($resources as $resource) {
            $sql = $resource::getEloquentQuery()->toSql();

            expect($sql)
                ->toContain('owner_type')
                ->toContain('owner_id');
        }
    });
});

it('prevents cross-tenant reads and writes on admin payout and fraud pages', function (): void {
    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        Permission::firstOrCreate(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);

        $ownerA->givePermissionTo('affiliates.payout.update');
        $ownerA->givePermissionTo('affiliates.fraud.update');
    });

    $this->actingAs($ownerA);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    [$affiliateA, $payoutA, $signalA] = OwnerContext::withOwner($ownerA, function (): array {
        $affiliate = Affiliate::create([
            'code' => 'A-' . Str::uuid(),
            'name' => 'Affiliate A',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'reference' => 'PAY-A-' . Str::uuid(),
            'status' => PendingPayout::class,
            'total_minor' => 5000,
            'currency' => 'USD',
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'velocity',
            'risk_points' => 80,
            'severity' => FraudSeverity::Critical,
            'description' => 'Velocity abuse detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        return [$affiliate, $payout, $signal];
    });

    [$affiliateB, $payoutB, $signalB] = OwnerContext::withOwner($ownerB, function (): array {
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
            'total_minor' => 7000,
            'currency' => 'USD',
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'pattern',
            'risk_points' => 40,
            'severity' => FraudSeverity::Medium,
            'description' => 'Suspicious pattern detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        return [$affiliate, $payout, $signal];
    });

    OwnerContext::withOwner($ownerA, function () use ($payoutA, $signalA, $payoutB, $signalB): void {
        $payoutPage = new PayoutBatchPage;
        $payoutData = $payoutPage->getViewData();

        expect($payoutData['pendingCount'])->toBe(1);

        $fraudPage = new FraudReviewPage;
        $fraudData = $fraudPage->getViewData();

        expect($fraudData['pendingCount'])->toBe(1)
            ->and($fraudData['criticalCount'])->toBe(1);

        $payoutTable = $payoutPage->table(Table::make($payoutPage));
        $reject = $payoutTable->getAction('reject');
        expect($reject)->not->toBeNull();

        expect(fn () => $reject?->call([
            'record' => $payoutB,
            'data' => ['reason' => 'Cross-tenant attempt'],
        ]))->toThrow(AuthorizationException::class);

        $fraudTable = $fraudPage->table(Table::make($fraudPage));
        $approve = $fraudTable->getAction('approve');
        expect($approve)->not->toBeNull();

        expect(fn () => $approve?->call(['record' => $signalB]))
            ->toThrow(ModelNotFoundException::class);

        // Sanity: same-tenant writes still possible.
        $reject?->call([
            'record' => $payoutA,
            'data' => ['reason' => 'Legitimate reject'],
        ]);

        $approve?->call(['record' => $signalA]);
    });
});

it('projects compliance names and searches only strict current-owner records', function (): void {
    config()->set('affiliates.owner.include_global', true);

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $create = function (string $name): Affiliate {
        $affiliate = Affiliate::create([
            'code' => 'COMPLIANCE-' . Str::uuid(),
            'name' => $name,
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        AffiliateSupportTicket::create([
            'affiliate_id' => $affiliate->id,
            'subject' => 'Test support',
            'category' => 'general',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099',
            'tax_year' => 2025,
            'status' => 'pending',
            'total_amount_minor' => 100,
            'currency' => 'USD',
        ]);

        return $affiliate;
    };

    $owned = OwnerContext::withOwner($owner, fn (): Affiliate => $create('Owned Compliance'));
    $foreign = OwnerContext::withOwner($other, fn (): Affiliate => $create('Foreign Compliance'));
    $global = OwnerContext::withOwner(null, fn (): Affiliate => $create('Global Compliance'));

    OwnerContext::withOwner($owner, function () use ($owned, $foreign, $global): void {
        foreach ([AffiliateSupportTicketResource::class, AffiliateTaxDocumentResource::class] as $resource) {
            $query = $resource::getEloquentQuery();

            expect((clone $query)->get()->pluck('affiliate_name')->all())->toBe(['Owned Compliance'])
                ->and(AffiliateReferenceProjection::search(clone $query, 'Foreign')->exists())->toBeFalse()
                ->and(AffiliateReferenceProjection::search(clone $query, 'Owned')->count())->toBe(1);
        }

        expect(AffiliateReferenceProjection::options('Compliance'))->toBe([$owned->id => 'Owned Compliance'])
            ->and(AffiliateReferenceProjection::label($owned->id))->toBe('Owned Compliance')
            ->and(AffiliateReferenceProjection::label($foreign->id))->toBeNull()
            ->and(AffiliateReferenceProjection::label($global->id))->toBeNull();
    });
});

it('rejects changes to an existing affiliate owner through administration', function (): void {
    config()->set('auth.providers.users.model', User::class);

    $owner = User::factory()->create();
    $other = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $other): void {
        $affiliate = Affiliate::create([
            'code' => 'IMMUTABLE-OWNER-' . Str::uuid(),
            'name' => 'Immutable Owner',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        $administration = app(AffiliateAdministration::class);

        expect(fn () => $administration->update($affiliate->id, [
            'linked_user' => $other->getKey(),
            'name' => 'Attempted Transfer',
        ]))->toThrow(ValidationException::class, 'The affiliate owner cannot be changed after creation.');

        $affiliate->refresh();
        expect((string) $affiliate->owner_id)->toBe((string) $owner->getKey())
            ->and($affiliate->name)->toBe('Immutable Owner');

        $administration->update($affiliate->id, [
            'linked_user' => $owner->getKey(),
            'description' => 'Allowed edit',
        ]);

        expect($affiliate->fresh()->description)->toBe('Allowed edit');
    });
});
