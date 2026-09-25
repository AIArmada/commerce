<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\MerchantCatalog;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Data\ExternalConversion;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateProgram;
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
});
