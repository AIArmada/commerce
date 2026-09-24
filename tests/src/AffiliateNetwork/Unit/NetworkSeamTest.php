<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Actions\PostNetworkConversionToLedger;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\LinkedProgramBridge;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkMembership;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderInterface;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderResolver;
use AIArmada\AffiliateNetwork\Services\Catalog\RemoteCatalogClient;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Network\AffiliatesIdentityResolver;
use AIArmada\Affiliates\Network\AffiliatesLedger;
use AIArmada\Affiliates\Network\AffiliatesProgramBridge;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

function networkPackageRoot(): string
{
    return dirname(__DIR__, 4) . '/packages';
}

/**
 * @return array<int, string>
 */
function phpFilesWithNeedle(string $dir, string $needle): array
{
    $hits = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        if (str_contains((string) file_get_contents($file->getPathname()), $needle)) {
            $hits[] = $file->getPathname();
        }
    }

    sort($hits);

    return $hits;
}

final class FakeAffiliateIdentities implements AffiliateIdentityResolver
{
    /** @param array<string, NetworkAffiliate> $affiliates */
    public function __construct(private array $affiliates = []) {}

    public function find(string $affiliateId): ?NetworkAffiliate
    {
        return $this->affiliates[$affiliateId] ?? null;
    }

    public function findAccessible(string $affiliateId): ?NetworkAffiliate
    {
        return $this->find($affiliateId);
    }

    public function findIdForVerifiedEmail(string $email): ?string
    {
        foreach ($this->affiliates as $affiliate) {
            if ($affiliate->email === $email) {
                return $affiliate->id;
            }
        }

        return null;
    }
}

final class FakeNetworkLedger implements NetworkLedger
{
    /** @var array<int, NetworkConversionDraft> */
    public array $posted = [];

    public function post(NetworkConversionDraft $draft): ?NetworkPostedConversion
    {
        $this->posted[] = $draft;

        return new NetworkPostedConversion(
            id: 'posted-' . $draft->externalReference,
            affiliateCode: 'FAKE',
            commissionMinor: $draft->commissionMinor,
            commissionCurrency: $draft->currency,
            status: 'posted',
        );
    }

    public function findPosted(string $linkId, string $externalReference): ?NetworkPostedConversion
    {
        foreach ($this->posted as $draft) {
            if ($draft->linkId === $linkId && $draft->externalReference === $externalReference) {
                return new NetworkPostedConversion(
                    id: 'posted-' . $draft->externalReference,
                    affiliateCode: 'FAKE',
                    commissionMinor: $draft->commissionMinor,
                    commissionCurrency: $draft->currency,
                    status: 'posted',
                );
            }
        }

        return null;
    }

    public function rowsForLink(string $linkId): array
    {
        $rows = [];

        foreach ($this->posted as $draft) {
            if ($draft->linkId === $linkId) {
                $rows[] = [
                    'commission_currency' => $draft->currency,
                    'value_minor' => $draft->revenueMinor,
                    'commission_minor' => $draft->commissionMinor,
                ];
            }
        }

        return $rows;
    }
}

final class FakeLinkedPrograms implements LinkedProgramBridge
{
    /** @param array<string, NetworkMembership> $memberships keyed by program id */
    public function __construct(private array $memberships = []) {}

    public function join(string $affiliateId, string $programId): NetworkMembership
    {
        return $this->memberships[$programId] ?? new NetworkMembership(
            id: 'membership-' . $programId,
            affiliateId: $affiliateId,
            programId: $programId,
            status: 'approved',
        );
    }

    public function membershipsFor(string $affiliateId, array $programIds): array
    {
        return array_filter(
            $this->memberships,
            fn (NetworkMembership $membership, string $programId): bool => in_array($programId, $programIds, true)
                && $membership->affiliateId === $affiliateId,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function existingProgramIds(array $programIds): array
    {
        return array_values(array_intersect($programIds, array_keys($this->memberships)));
    }

    public function approvedProgramIds(string $affiliateId, int $limit = 500): array
    {
        $ids = [];

        foreach ($this->memberships as $programId => $membership) {
            if ($membership->affiliateId === $affiliateId && $membership->isApproved()) {
                $ids[] = $programId;
            }
        }

        return array_slice($ids, 0, max(1, $limit));
    }
}

final class FakeLocalCatalogReader implements CatalogReaderInterface
{
    public function snapshot(AffiliateSite $site, string $programId): array
    {
        return ['program_id' => $programId, 'subjects' => []];
    }

    public function programIds(AffiliateSite $site): array
    {
        return [];
    }
}

function seamFixtures(string $domain): array
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => $domain]);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => "https://{$domain}/landing",
        'requires_approval' => false,
        'rate_base_bp' => 1500,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);

    return [$site, $offer];
}

describe('Network seam', function (): void {
    test('network and filament-network sources never reference affiliates classes', function (): void {
        $root = networkPackageRoot();

        $hits = [
            ...phpFilesWithNeedle($root . '/affiliate-network/src', 'AIArmada\\Affiliates'),
            ...phpFilesWithNeedle($root . '/affiliate-network/database', 'AIArmada\\Affiliates'),
            ...phpFilesWithNeedle($root . '/affiliate-network/config', 'AIArmada\\Affiliates'),
            ...phpFilesWithNeedle($root . '/affiliate-network/routes', 'AIArmada\\Affiliates'),
            ...phpFilesWithNeedle($root . '/filament-affiliate-network/src', 'AIArmada\\Affiliates'),
        ];

        expect($hits)->toBe([]);
    });

    test('applications file against a fake identity without affiliates models', function (): void {
        [, $offer] = seamFixtures('seam-apply.example');

        $identities = new FakeAffiliateIdentities([
            'aff-1' => new NetworkAffiliate(id: 'aff-1', code: 'SEAM1'),
        ]);

        $application = (new ApplyToOffer($identities))->execute($offer, 'aff-1', 'Seam application');

        expect($application->affiliate_id)->toBe('aff-1')
            ->and($application->offer_id)->toBe($offer->id);
    });

    test('unknown affiliates cannot apply', function (): void {
        [, $offer] = seamFixtures('seam-unknown.example');

        expect(fn () => (new ApplyToOffer(new FakeAffiliateIdentities))->execute($offer, 'ghost'))
            ->toThrow(ModelNotFoundException::class);
    });

    test('commission math stays in the network and reaches the ledger on the draft', function (): void {
        [, $offer] = seamFixtures('seam-math.example');

        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-1')
            ->create(['currency' => 'MYR']);

        $ledger = new FakeNetworkLedger;

        $posted = (new PostNetworkConversionToLedger($ledger))->execute($link, 89900, 'MYR', 'ORDER-1');

        expect($posted)->not->toBeNull()
            ->and($ledger->posted)->toHaveCount(1)
            ->and($ledger->posted[0]->commissionMinor)->toBe(13485)
            ->and($ledger->posted[0]->currency)->toBe('MYR')
            ->and($ledger->posted[0]->affiliateId)->toBe('aff-1');
    });

    test('fixed-rate offers post the fixed commission', function (): void {
        [$site] = seamFixtures('seam-fixed.example');
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-fixed.example/landing',
            'requires_approval' => false,
            'rate_base_bp' => null,
            'rate_fixed_minor' => 2500,
            'currency' => 'MYR',
        ]);
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        $ledger = new FakeNetworkLedger;

        (new PostNetworkConversionToLedger($ledger))->execute($link, 99999, null, 'ORDER-2');

        expect($ledger->posted)->toHaveCount(1)
            ->and($ledger->posted[0]->commissionMinor)->toBe(2500);
    });

    test('posting without a ledger keeps counters only', function (): void {
        [, $offer] = seamFixtures('seam-noledger.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        expect((new PostNetworkConversionToLedger)->execute($link, 5000, 'MYR', 'ORDER-3'))->toBeNull();
    });

    test('reconciliation without a ledger fails explicitly', function (): void {
        [, $offer] = seamFixtures('seam-reconcile.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        expect(fn () => (new NetworkLedgerReconciliationService)->reconcileLink($link))
            ->toThrow(AffiliatesNotInstalled::class);
    });

    test('reconciliation runs against a fake ledger', function (): void {
        [, $offer] = seamFixtures('seam-reconcile-fake.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create(['currency' => 'MYR']);

        $ledger = new FakeNetworkLedger;
        (new PostNetworkConversionToLedger($ledger))->execute($link, 5000, 'MYR', 'ORDER-4');
        $link->recordConversion(5000);

        $report = (new NetworkLedgerReconciliationService($ledger))->reconcileLink($link->fresh());

        expect($report['match'])->toBeTrue();
    });

    test('linked-program features degrade to the network flow without a bridge', function (): void {
        [$site] = seamFixtures('seam-remote.example');
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-remote.example/landing',
            'requires_approval' => true,
            'visibility' => OfferVisibility::Public,
            'external_program_id' => 'program-vanished',
            'metadata' => ['catalog_source' => 'local'],
        ]);

        $management = app(OfferManagementService::class);

        // Construct the remote-only shape directly: no program bridge bound.
        $remoteOnly = new OfferManagementService(
            app(CreateOffer::class),
            app(ApplyToOffer::class),
            app(ApproveApplication::class),
            null,
        );

        expect($management->isLocalProgramOffer($offer))->toBeTrue()
            ->and($remoteOnly->enrollInLinkedProgram($offer, 'aff-1'))->toBeNull()
            ->and($remoteOnly->hasAppliedForOffer($offer, 'aff-1'))->toBeFalse()
            ->and($remoteOnly->isApprovedForOffer($offer, 'aff-1'))->toBeFalse();
    });

    test('program memberships resolve through the bridge', function (): void {
        [$site] = seamFixtures('seam-bridge.example');
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-bridge.example/landing',
            'requires_approval' => true,
            'visibility' => OfferVisibility::Public,
            'external_program_id' => 'program-1',
            'metadata' => ['catalog_source' => 'local'],
        ]);

        $management = new OfferManagementService(
            app(CreateOffer::class),
            app(ApplyToOffer::class),
            app(ApproveApplication::class),
            new FakeLinkedPrograms([
                'program-1' => new NetworkMembership(id: 'm-1', affiliateId: 'aff-1', programId: 'program-1', status: 'approved'),
            ]),
        );

        expect($management->isApprovedForOffer($offer, 'aff-1'))->toBeTrue()
            ->and($management->hasAppliedForOffer($offer, 'aff-1'))->toBeTrue()
            ->and($management->applicationStatusForOffer($offer, 'aff-1'))->toBe('approved')
            ->and($management->enrollInLinkedProgram($offer, 'aff-1'))->not->toBeNull();
    });

    test('status maps batch network and program offers', function (): void {
        [$site, $offer] = seamFixtures('seam-map.example');
        $programOffer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-map.example/program',
            'requires_approval' => true,
            'visibility' => OfferVisibility::Public,
            'external_program_id' => 'program-9',
            'metadata' => ['catalog_source' => 'local'],
        ]);
        AffiliateOfferApplication::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-1')
            ->approved()
            ->create();

        $management = new OfferManagementService(
            app(CreateOffer::class),
            app(ApplyToOffer::class),
            app(ApproveApplication::class),
            new FakeLinkedPrograms([
                'program-9' => new NetworkMembership(id: 'm-9', affiliateId: 'aff-1', programId: 'program-9', status: 'pending'),
            ]),
        );

        $map = $management->applicationStatusMap('aff-1', new Collection([$offer, $programOffer]));

        expect($map[(string) $offer->getKey()])->toBe('approved')
            ->and($map[(string) $programOffer->getKey()])->toBe('pending');
    });

    test('affiliate relations fail explicitly without a bound model', function (): void {
        [, $offer] = seamFixtures('seam-relation.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        config(['affiliate-network.models.affiliate' => null]);

        expect(fn () => $link->affiliate()->first())->toThrow(AffiliatesNotInstalled::class);
    });

    test('affiliates package binds the network seam adapters', function (): void {
        expect(app()->bound(AffiliateIdentityResolver::class))->toBeTrue()
            ->and(app(AffiliateIdentityResolver::class))->toBeInstanceOf(AffiliatesIdentityResolver::class)
            ->and(app(NetworkLedger::class))->toBeInstanceOf(AffiliatesLedger::class)
            ->and(app(LinkedProgramBridge::class))->toBeInstanceOf(AffiliatesProgramBridge::class)
            ->and(app()->bound(CatalogReaderResolver::LOCAL_READER_KEY))->toBeTrue()
            ->and(config('affiliate-network.models.affiliate'))->toBe(Affiliate::class);
    });

    test('catalog resolver prefers remote readers and fails explicitly for local without adapters', function (): void {
        $resolver = app(CatalogReaderResolver::class);

        $remoteSite = AffiliateSite::factory()->create([
            'domain' => 'seam-remote-' . uniqid() . '.example',
            'catalog_url' => 'https://merchant.example/catalog',
        ]);
        expect($resolver->readerFor($remoteSite))->toBeInstanceOf(RemoteCatalogClient::class);

        app()->offsetUnset(CatalogReaderResolver::LOCAL_READER_KEY);

        $localSite = AffiliateSite::factory()->create([
            'domain' => 'seam-local-' . uniqid() . '.example',
            'catalog_url' => null,
        ]);
        expect(fn () => $resolver->readerFor($localSite))->toThrow(AffiliatesNotInstalled::class);

        app()->bind(CatalogReaderResolver::LOCAL_READER_KEY, FakeLocalCatalogReader::class);
        expect($resolver->readerFor($localSite))->toBeInstanceOf(FakeLocalCatalogReader::class);
    });
});
