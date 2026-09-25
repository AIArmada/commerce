<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesIdentityReader;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesLedgerPoster;
use AIArmada\AffiliateNetwork\Adapters\Affiliates\EnginePayoutFulfillment;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Contracts\Fulfillment;
use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Data\NetworkPostedConversion;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Exceptions\AffiliatesNotInstalled;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderInterface;
use AIArmada\AffiliateNetwork\Services\Catalog\CatalogReaderResolver;
use AIArmada\AffiliateNetwork\Services\Catalog\RemoteCatalogClient;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;
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

    public function postingsForExternalReference(string $externalReference): array
    {
        $rows = [];

        foreach ($this->posted as $draft) {
            if ($draft->externalReference === $externalReference) {
                $rows[] = [
                    'origin' => 'marketplace',
                    'source_ref' => $draft->linkId,
                    'commission_currency' => $draft->currency,
                    'commission_minor' => $draft->commissionMinor,
                ];
            }
        }

        return $rows;
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
    test('affiliates references live only in network adapters', function (): void {
        $root = networkPackageRoot();

        $adapterHits = phpFilesWithNeedle($root . '/affiliate-network/src/Adapters', 'AIArmada\\Affiliates');
        $otherSrcHits = array_values(array_filter(
            phpFilesWithNeedle($root . '/affiliate-network/src', 'AIArmada\\Affiliates'),
            fn (string $path): bool => ! str_contains($path, '/src/Adapters/'),
        ));

        expect($adapterHits)->not->toBe([])
            ->and($otherSrcHits)->toHaveCount(1)
            ->and($otherSrcHits[0])->toEndWith('AffiliateNetworkServiceProvider.php')
            ->and(phpFilesWithNeedle($root . '/affiliate-network/database', 'AIArmada\\Affiliates'))->toBe([])
            ->and(phpFilesWithNeedle($root . '/affiliate-network/config', 'AIArmada\\Affiliates'))->toBe([])
            ->and(phpFilesWithNeedle($root . '/affiliate-network/routes', 'AIArmada\\Affiliates'))->toBe([])
            ->and(phpFilesWithNeedle($root . '/filament-affiliate-network/src', 'AIArmada\\Affiliates'))->toBe([]);
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

    test('commission math stays in the network books', function (): void {
        [, $offer] = seamFixtures('seam-math.example');

        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-1')
            ->create(['currency' => 'MYR']);

        $leg = app(NetworkBooks::class)->post($link, 89900, 'MYR', 'ORDER-1');

        expect($leg->commission_minor)->toBe(13485)
            ->and($leg->commission_currency)->toBe('MYR')
            ->and($leg->affiliate_id)->toBe('aff-1')
            ->and($leg->payout_minor)->toBe(13485)
            ->and($leg->fee_minor)->toBe(0)
            ->and($leg->status)->toBe(LegStatus::Posted);
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

        $leg = app(NetworkBooks::class)->post($link, 99999, null, 'ORDER-2');

        expect($leg->commission_minor)->toBe(2500)
            ->and($leg->commission_currency)->toBe('MYR');
    });

    test('recording without a reference keeps counters only', function (): void {
        [, $offer] = seamFixtures('seam-noledger.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        $leg = app(OfferLinkService::class)->recordConversion($link, 5000, 'MYR');

        expect($leg)->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($link->fresh()->conversions)->toBe(1);
    });

    test('reconciliation runs standalone on legs', function (): void {
        [, $offer] = seamFixtures('seam-reconcile.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        app(OfferLinkService::class)->recordConversion($link, 5000, 'MYR', 'ORDER-3');

        $report = (new NetworkLedgerReconciliationService)->reconcileLink($link->fresh());

        expect($report['match'])->toBeTrue()
            ->and($report['ledger'])->toBeNull()
            ->and($report['legs']['posted'])->toBe(1);
    });

    test('reconciliation runs against a fake ledger', function (): void {
        [, $offer] = seamFixtures('seam-reconcile-fake.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create(['currency' => 'MYR']);

        app(OfferLinkService::class)->recordConversion($link, 5000, 'MYR', 'ORDER-4');

        $leg = NetworkConversionLeg::query()->where('external_reference', 'ORDER-4')->firstOrFail();
        $ledger = new FakeNetworkLedger;
        $ledger->post(new NetworkConversionDraft(
            linkId: (string) $link->getKey(),
            offerId: (string) $offer->getKey(),
            siteId: null,
            affiliateId: 'aff-1',
            linkCode: 'X',
            revenueMinor: 5000,
            currency: 'MYR',
            externalReference: 'ORDER-4',
            commissionMinor: $leg->payout_minor,
        ));

        $report = (new NetworkLedgerReconciliationService($ledger))->reconcileLink($link->fresh());

        expect($report['match'])->toBeTrue();
    });

    test('mirrored program offers use the network application flow', function (): void {
        [$site] = seamFixtures('seam-mirror.example');
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-mirror.example/landing',
            'requires_approval' => true,
            'visibility' => OfferVisibility::Public,
            'external_program_id' => 'program-vanished',
            'metadata' => ['catalog_source' => 'local'],
        ]);

        $management = app(OfferManagementService::class);

        expect($management->hasAppliedForOffer($offer, 'aff-1'))->toBeFalse()
            ->and($management->isApprovedForOffer($offer, 'aff-1'))->toBeFalse()
            ->and($management->applicationStatusForOffer($offer, 'aff-1'))->toBeNull();

        AffiliateOfferApplication::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-1')
            ->approved()
            ->create();

        expect($management->hasAppliedForOffer($offer, 'aff-1'))->toBeTrue()
            ->and($management->isApprovedForOffer($offer, 'aff-1'))->toBeTrue()
            ->and($management->applicationStatusForOffer($offer, 'aff-1'))->toBe('approved');
    });

    test('approvals for mirrored offers need no merchant membership', function (): void {
        [$site] = seamFixtures('seam-mirror-approve.example');
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'landing_url' => 'https://seam-mirror-approve.example/landing',
            'requires_approval' => true,
            'visibility' => OfferVisibility::Public,
            'external_program_id' => 'program-1',
            'metadata' => ['catalog_source' => 'local'],
        ]);
        $application = AffiliateOfferApplication::factory()
            ->forOffer($offer)
            ->forAffiliateId('aff-1')
            ->create();

        $management = app(OfferManagementService::class);
        $management->approveApplication($application);

        expect($management->isApprovedForOffer($offer, 'aff-1'))->toBeTrue()
            ->and($management->applicationStatusForOffer($offer, 'aff-1'))->toBe('approved');
    });

    test('status maps batch plain and mirrored offers in one flow', function (): void {
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
        AffiliateOfferApplication::factory()
            ->forOffer($programOffer)
            ->forAffiliateId('aff-1')
            ->pending()
            ->create();

        $map = app(OfferManagementService::class)->applicationStatusMap('aff-1', new Collection([$offer, $programOffer]));

        expect($map[(string) $offer->getKey()])->toBe('approved')
            ->and($map[(string) $programOffer->getKey()])->toBe('pending');
    });

    test('affiliate relations fail explicitly without a bound model', function (): void {
        [, $offer] = seamFixtures('seam-relation.example');
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId('aff-1')->create();

        config(['affiliate-network.models.affiliate' => null]);

        expect(fn () => $link->affiliate()->first())->toThrow(AffiliatesNotInstalled::class);
    });

    test('network binds the engine adapters when the engine is installed', function (): void {
        expect(app()->bound(AffiliateIdentityResolver::class))->toBeTrue()
            ->and(app(AffiliateIdentityResolver::class))->toBeInstanceOf(AffiliatesIdentityReader::class)
            ->and(app(NetworkLedger::class))->toBeInstanceOf(AffiliatesLedgerPoster::class)
            ->and(app(Fulfillment::class))->toBeInstanceOf(EnginePayoutFulfillment::class)
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
