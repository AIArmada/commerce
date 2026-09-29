<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\MerchantCatalog;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Data\PostedConversion;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Str;

function merchantSeamProgram(array $attributes = []): AffiliateProgram
{
    return AffiliateProgram::create(array_merge([
        'name' => 'Seam Program',
        'slug' => 'seam-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
    ], $attributes));
}

function merchantSeamDraft(string $affiliateId, array $overrides = []): ExternalConversion
{
    return new ExternalConversion(...array_merge([
        'source' => 'marketplace',
        'sourceRef' => (string) Str::uuid(),
        'affiliateId' => $affiliateId,
        'revenueMinor' => 89900,
        'currency' => 'MYR',
        'externalReference' => 'ORDER-SEAM-' . uniqid(),
        'commissionMinor' => 13485,
    ], $overrides));
}

describe('merchant seam', function (): void {
    test('ledger posts an external conversion idempotently', function (): void {
        $affiliate = createTestAffiliate();
        $draft = merchantSeamDraft((string) $affiliate->getKey());
        $ledger = app(MerchantLedger::class);

        $first = $ledger->postExternalConversion($draft);
        $second = $ledger->postExternalConversion($draft);

        expect($first)->not->toBeNull()
            ->and($second)->not->toBeNull()
            ->and($first->id)->toBe($second->id)
            ->and($first->affiliateCode)->toBe($affiliate->code)
            ->and(AffiliateConversion::query()->where('origin', 'marketplace')->count())->toBe(1);
    });

    test('ledger maps unknown ids to the email-linked affiliate', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'seam-linked-' . uniqid() . '@example.com']);
        $draft = merchantSeamDraft((string) Str::uuid(), ['affiliateEmail' => $affiliate->contact_email]);

        $posted = app(MerchantLedger::class)->postExternalConversion($draft);

        expect($posted)->not->toBeNull()
            ->and($posted->affiliateCode)->toBe($affiliate->code);
    });

    test('ledger posts nothing for unresolvable affiliates', function (): void {
        $draft = merchantSeamDraft((string) Str::uuid());

        expect(app(MerchantLedger::class)->postExternalConversion($draft))->toBeNull()
            ->and(AffiliateConversion::query()->exists())->toBeFalse();
    });

    test('ledger finds postings for reconciliation', function (): void {
        $affiliate = createTestAffiliate();
        $draft = merchantSeamDraft((string) $affiliate->getKey());
        $ledger = app(MerchantLedger::class);
        $ledger->postExternalConversion($draft);

        expect($ledger->findPosted('marketplace', $draft->sourceRef))->not->toBeNull()
            ->and($ledger->findPosted('marketplace', 'missing'))->toBeNull()
            ->and($ledger->postingsForSourceRef('marketplace', $draft->sourceRef))->toHaveCount(1);
    });

    test('identity resolves affiliates by id and email', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'seam-id-' . uniqid() . '@example.com']);
        $identities = app(MerchantIdentity::class);

        expect($identities->find((string) $affiliate->getKey())?->code)->toBe($affiliate->code)
            ->and($identities->find((string) Str::uuid()))->toBeNull()
            ->and($identities->findIdForEmail($affiliate->contact_email))->toBe((string) $affiliate->getKey())
            ->and($identities->findIdForEmail('nobody-' . uniqid() . '@example.com'))->toBeNull();
    });

    test('catalog snapshots programs for mirrors', function (): void {
        $program = merchantSeamProgram();
        $catalog = app(MerchantCatalog::class);

        $snapshot = $catalog->snapshotById((string) $program->getKey());

        expect($snapshot)->not->toBeNull()
            ->and($snapshot->programId)->toBe((string) $program->getKey())
            ->and($snapshot->payload['program_id'])->toBe((string) $program->getKey())
            ->and($catalog->snapshotById((string) Str::uuid()))->toBeNull()
            ->and($catalog->mirrorableProgramIds())->toContain((string) $program->getKey());
    });

    test('ledger voids a posting with balanced money', function (): void {
        $affiliate = createTestAffiliate();
        $draft = merchantSeamDraft((string) $affiliate->getKey());
        $ledger = app(MerchantLedger::class);
        $posted = $ledger->postExternalConversion($draft);

        expect($posted)->not->toBeNull()
            ->and($affiliate->balanceFor('MYR')->holding_minor)->toBe(13485);

        $voided = $ledger->voidPosted('marketplace', $draft->sourceRef, $draft->externalReference, 'merchant refund');

        expect($voided)->not->toBeNull()
            ->and($voided->id)->toBe($posted->id)
            ->and($voided->commissionMinor)->toBe(13485);

        $conversion = AffiliateConversion::query()->withoutOwnerScope()->findOrFail($posted->id);
        $balance = $affiliate->balanceFor('MYR')->fresh();

        expect($conversion->status->equals(RejectedConversion::class))->toBeTrue()
            ->and($balance->holding_minor)->toBe(0)
            ->and($balance->available_minor)->toBe(0)
            ->and($balance->lifetime_earnings_minor)->toBe(0);
    });

    test('ledger void returns null for unknown postings', function (): void {
        expect(app(MerchantLedger::class)->voidPosted('marketplace', 'missing', 'missing', 'x'))->toBeNull();
    });

    test('ledger voids an owned posting without an ambient owner context', function (): void {
        config()->set('affiliates.owner.enabled', true);
        config()->set('affiliates.owner.include_global', false);

        $owner = User::query()->create([
            'name' => 'Ledger Owner',
            'email' => 'ledger-owner-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $affiliate = OwnerContext::withOwner($owner, fn (): Affiliate => createTestAffiliate());
        $draft = merchantSeamDraft((string) $affiliate->getKey());
        $ledger = app(MerchantLedger::class);
        $posted = OwnerContext::withOwner($owner, fn (): ?PostedConversion => $ledger->postExternalConversion($draft));

        expect($posted)->not->toBeNull();

        // The network reversal path is deliberately cross-tenant: no
        // ambient owner is resolved when voidPosted runs.
        $voided = OwnerContext::withOwner(null, fn (): ?PostedConversion => $ledger->voidPosted(
            'marketplace',
            $draft->sourceRef,
            $draft->externalReference,
            'network reversal'
        ));

        expect($voided)->not->toBeNull()
            ->and($voided->id)->toBe($posted->id);

        $conversion = AffiliateConversion::query()->withoutOwnerScope()->findOrFail($posted->id);

        expect($conversion->status->equals(RejectedConversion::class))->toBeTrue();
    });
});
