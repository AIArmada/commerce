<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\CreateSite;
use AIArmada\AffiliateNetwork\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Actions\VerifySite;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

describe('site lifecycle actions', function (): void {
    test('validates normalized domain uniqueness and catalog URL storage length', function (): void {
        $site = AffiliateSite::factory()->create(['domain' => 'merchant.example']);

        expect(fn () => app(CreateSite::class)->execute([
            'name' => 'Duplicate',
            'domain' => ' MERCHANT.EXAMPLE ',
        ]))->toThrow(ValidationException::class);

        expect(fn () => app(UpdateSite::class)->execute($site, [
            'name' => $site->name,
            'domain' => $site->domain,
            'catalog_url' => 'https://merchant.example/' . str_repeat('a', 255),
        ]))->toThrow(ValidationException::class);
    });

    test('rejects an explicit null status and keeps unverified sites unstamped', function (): void {
        $site = app(CreateSite::class)->execute([
            'name' => 'Pending merchant',
            'domain' => 'pending.example',
            'verified_at' => '2025-01-01',
        ]);

        expect($site->verified_at)->toBeNull();

        $data = ['name' => $site->name, 'domain' => $site->domain];
        $updated = app(UpdateSite::class)->execute($site, $data + ['verified_at' => '2025-01-01']);

        expect($updated->verified_at)->toBeNull();
        expect(fn () => app(UpdateSite::class)->execute($site, $data + ['status' => null]))
            ->toThrow(ValidationException::class);
    });

    test('verifies a pending site and stamps verified_at', function (): void {
        $site = AffiliateSite::factory()->pending()->create();

        $verified = app(VerifySite::class)->execute($site);

        expect($verified->status)->toBe(AffiliateSite::STATUS_VERIFIED)
            ->and($verified->verified_at)->not->toBeNull()
            ->and($verified->isVerified())->toBeTrue();
    });

    test('rejects a pending site', function (): void {
        $site = AffiliateSite::factory()->pending()->create();

        $rejected = app(RejectSite::class)->execute($site);

        expect($rejected->status)->toBe(AffiliateSite::STATUS_REJECTED);
    });

    test('suspends and reinstates a verified site', function (): void {
        $site = AffiliateSite::factory()->verified()->create();

        $suspended = app(SuspendSite::class)->execute($site);

        expect($suspended->status)->toBe(AffiliateSite::STATUS_SUSPENDED)
            ->and($suspended->verified_at)->not->toBeNull();

        $reinstated = app(ReinstateSite::class)->execute($suspended);

        expect($reinstated->status)->toBe(AffiliateSite::STATUS_VERIFIED)
            ->and($reinstated->isVerified())->toBeTrue();
    });

    test('refuses transitions from the wrong state', function (): void {
        $verified = AffiliateSite::factory()->verified()->create();
        $pending = AffiliateSite::factory()->pending()->create();

        expect(fn () => app(VerifySite::class)->execute($verified))->not->toThrow(RuntimeException::class);
        expect(fn () => app(RejectSite::class)->execute($verified))->toThrow(RuntimeException::class);
        expect(fn () => app(SuspendSite::class)->execute($pending))->toThrow(RuntimeException::class);
        expect(fn () => app(ReinstateSite::class)->execute($pending))->toThrow(RuntimeException::class);
    });

    test('owner-scoped transitions require the site owner context', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $owner = User::factory()->create();
        $other = User::factory()->create();

        $site = OwnerContext::withOwner($owner, fn (): AffiliateSite => AffiliateSite::factory()
            ->pending()
            ->forOwner($owner)
            ->create());

        $verified = OwnerContext::withOwner($owner, fn (): AffiliateSite => app(VerifySite::class)->execute($site));

        expect($verified->status)->toBe(AffiliateSite::STATUS_VERIFIED);

        expect(fn () => OwnerContext::withOwner($other, fn (): AffiliateSite => app(SuspendSite::class)->execute($verified)))
            ->toThrow(ModelNotFoundException::class);
    });
});
