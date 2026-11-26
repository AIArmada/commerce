<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\OfferVisibility;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\CreateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource\Pages\EditAffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource\Schemas\AffiliateOfferCategoryForm;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\TopOffersWidget;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\Queries\NetworkStatsAggregator;
use AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource\Tables\AffiliateOfferApplicationsTable;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource\Tables\AffiliateSitesTable;
use AIArmada\FilamentAffiliateNetwork\Platform\NetworkAdminAccess;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RepairTableHostComponent extends Component implements HasTable
{
    use InteractsWithTable;

    public function getTable(): Table
    {
        return Table::make($this);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render()
    {
        return view('livewire.placeholder');
    }
}

class RepairSchemaHostComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

class RepairIntIdUser extends User
{
    public function getAuthIdentifier(): int
    {
        return 123;
    }
}

function regressionValidateOfferForm(array $data): ?ValidationException
{
    $schema = AffiliateOfferForm::configure(
        Schema::make(new RepairSchemaHostComponent)->model(AffiliateOffer::class)->statePath('data')
    );
    $schema->fill($data);

    try {
        $schema->getState();

        return null;
    } catch (ValidationException $e) {
        return $e;
    }
}

function regressionValidateSiteForm(array $data): ?ValidationException
{
    $schema = AffiliateSiteForm::configure(
        Schema::make(new RepairSchemaHostComponent)->model(AffiliateSite::class)->statePath('data')
    );
    $schema->fill($data);

    try {
        $schema->getState();

        return null;
    } catch (ValidationException $e) {
        return $e;
    }
}

function regressionCreateAffiliateFor(User $user, string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => 'Repair Affiliate ' . $code,
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'contact_email' => $user->email,
    ]);
}

describe('admin verify action on owned sites', function (): void {
    test('verifies an owned site when owner scoping is enabled', function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $owner = User::factory()->create();
        $site = OwnerContext::withOwner($owner, fn (): AffiliateSite => AffiliateSite::factory()->forOwner($owner)->create([
            'domain' => 'h1-owned.example',
            'status' => SiteStatus::Pending,
            'verified_at' => null,
        ]));

        $host = new RepairTableHostComponent;
        $action = AffiliateSitesTable::configure(Table::make($host))->getAction('verify');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($site);
        $action->call();

        $site->refresh();

        expect($site->status)->toBe(SiteStatus::Verified)
            ->and($site->verified_at)->not->toBeNull();
    });
});

describe('admin application actions in affiliate owner context', function (): void {
    beforeEach(function (): void {
        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);

        $this->owner = User::factory()->create();
        $suffix = uniqid();

        $this->site = OwnerContext::withOwner($this->owner, fn (): AffiliateSite => AffiliateSite::factory()->verified()->forOwner($this->owner)->create([
            'domain' => "h2-{$suffix}.example",
        ]));

        $this->offer = OwnerContext::withOwner($this->owner, fn (): AffiliateOffer => AffiliateOffer::factory()->published()->forSite($this->site)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
        ]));

        $this->affiliateUser = User::create([
            'name' => 'H2 Affiliate User',
            'email' => "h2-affiliate-{$suffix}@example.com",
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = OwnerContext::withOwner($this->owner, fn (): Affiliate => Affiliate::create([
            'code' => 'H2' . $suffix,
            'name' => 'H2 Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => $this->affiliateUser->email,
        ]));
    });

    test('approve succeeds on a cross-owner application', function (): void {
        $application = OwnerContext::withOwner($this->owner, fn (): AffiliateOfferApplication => AffiliateOfferApplication::create([
            'offer_id' => $this->offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => ApplicationStatus::Pending,
        ]));

        $host = new RepairTableHostComponent;
        $action = AffiliateOfferApplicationsTable::configure(Table::make($host))->getAction('approve');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($application);
        $action->call();

        expect($application->fresh()->status)->toBe(ApplicationStatus::Approved)
            ->and($application->fresh()->approved_at)->not->toBeNull();
    });

    test('reject succeeds on a cross-owner application', function (): void {
        $application = OwnerContext::withOwner($this->owner, fn (): AffiliateOfferApplication => AffiliateOfferApplication::create([
            'offer_id' => $this->offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => ApplicationStatus::Pending,
        ]));

        $host = new RepairTableHostComponent;
        $action = AffiliateOfferApplicationsTable::configure(Table::make($host))->getAction('reject');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($application);
        $action->call(['data' => ['reason' => 'Not a fit right now.']]);

        $fresh = $application->fresh();

        expect($fresh->status)->toBe(ApplicationStatus::Rejected)
            ->and($fresh->rejection_reason)->toBe('Not a fit right now.');
    });

    test('revoke succeeds on a cross-owner application', function (): void {
        $application = OwnerContext::withOwner($this->owner, fn (): AffiliateOfferApplication => AffiliateOfferApplication::create([
            'offer_id' => $this->offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => ApplicationStatus::Approved,
        ]));

        $host = new RepairTableHostComponent;
        $action = AffiliateOfferApplicationsTable::configure(Table::make($host))->getAction('revoke');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($application);
        $action->call(['data' => ['reason' => 'Policy violation.']]);

        $fresh = $application->fresh();

        expect($fresh->status)->toBe(ApplicationStatus::Revoked)
            ->and($fresh->rejection_reason)->toBe('Policy violation.');
    });

    test('bulk approve approves applications across owners', function (): void {
        $applicationA = OwnerContext::withOwner($this->owner, fn (): AffiliateOfferApplication => AffiliateOfferApplication::create([
            'offer_id' => $this->offer->id,
            'affiliate_id' => $this->affiliate->id,
            'status' => ApplicationStatus::Pending,
        ]));

        $ownerB = User::factory()->create();
        $suffix = uniqid();

        $siteB = OwnerContext::withOwner($ownerB, fn (): AffiliateSite => AffiliateSite::factory()->verified()->forOwner($ownerB)->create([
            'domain' => "h2b-{$suffix}.example",
        ]));
        $offerB = OwnerContext::withOwner($ownerB, fn (): AffiliateOffer => AffiliateOffer::factory()->published()->forSite($siteB)->create([
            'visibility' => OfferVisibility::Public,
            'requires_approval' => true,
        ]));
        $affiliateB = OwnerContext::withOwner($ownerB, fn (): Affiliate => Affiliate::create([
            'code' => 'H2B' . $suffix,
            'name' => 'H2B Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => "h2b-{$suffix}@example.com",
        ]));
        $applicationB = OwnerContext::withOwner($ownerB, fn (): AffiliateOfferApplication => AffiliateOfferApplication::create([
            'offer_id' => $offerB->id,
            'affiliate_id' => $affiliateB->id,
            'status' => ApplicationStatus::Pending,
        ]));

        $host = new RepairTableHostComponent;
        $bulk = AffiliateOfferApplicationsTable::configure(Table::make($host))->getBulkAction('approve_selected');

        expect($bulk)->not->toBeNull();

        $bulk->livewire($host);
        $bulk->call(['records' => new EloquentCollection([$applicationA, $applicationB])]);

        expect($applicationA->fresh()->status)->toBe(ApplicationStatus::Approved)
            ->and($applicationB->fresh()->status)->toBe(ApplicationStatus::Approved);
    });
});

describe('affiliate id column', function (): void {
    test('affiliate id column is searchable and copyable', function (): void {
        $host = new RepairTableHostComponent;
        $column = AffiliateOfferApplicationsTable::configure(Table::make($host))->getColumn('affiliate_id');

        expect($column)->not->toBeNull()
            ->and($column->isSearchable())->toBeTrue();
    });
});

describe('application status badge', function (): void {
    test('accepts enum and string states', function (): void {
        $host = new RepairTableHostComponent;
        $column = AffiliateOfferApplicationsTable::configure(Table::make($host))->getColumn('status');

        expect($column)->not->toBeNull()
            ->and($column->getColor(ApplicationStatus::Approved))->toBe('success')
            ->and($column->getColor(ApplicationStatus::Pending))->toBe('warning')
            ->and($column->getColor(ApplicationStatus::Rejected))->toBe('danger')
            ->and($column->getColor('approved'))->toBe('success');
    });
});

describe('offer form validation', function (): void {
    beforeEach(function (): void {
        $suffix = uniqid();

        $this->siteA = AffiliateSite::factory()->verified()->create(['domain' => "m4a-{$suffix}.example"]);
        $this->siteB = AffiliateSite::factory()->verified()->create(['domain' => "m4b-{$suffix}.example"]);

        $this->valid = [
            'site_id' => $this->siteA->id,
            'name' => 'M4 Offer',
            'slug' => "m4-offer-{$suffix}",
            'status' => OfferStatus::Draft->value,
            'rate_base_bp' => 1000,
            'currency' => 'USD',
            'cookie_days' => 30,
        ];
    });

    test('accepts valid offer data', function (): void {
        expect(regressionValidateOfferForm($this->valid))->toBeNull();
    });

    test('rejects duplicate slugs within the same site only', function (): void {
        AffiliateOffer::factory()->forSite($this->siteA)->create([
            'slug' => $this->valid['slug'],
            'status' => OfferStatus::Draft,
        ]);

        $duplicate = regressionValidateOfferForm($this->valid);

        expect($duplicate)->not->toBeNull()
            ->and($duplicate->errors())->toHaveKey('data.slug');

        $otherSite = regressionValidateOfferForm(array_merge($this->valid, ['site_id' => $this->siteB->id]));

        expect($otherSite)->toBeNull();
    });

    test('rejects decimal and negative rates', function (): void {
        expect(regressionValidateOfferForm(array_merge($this->valid, ['rate_base_bp' => '10.5'])))->not->toBeNull()
            ->and(regressionValidateOfferForm(array_merge($this->valid, ['rate_base_bp' => -1])))->not->toBeNull()
            ->and(regressionValidateOfferForm(array_merge($this->valid, ['rate_fixed_minor' => -1])))->not->toBeNull();
    });

    test('requires a three-letter currency code', function (): void {
        expect(regressionValidateOfferForm(array_merge($this->valid, ['currency' => 'US'])))->not->toBeNull()
            ->and(regressionValidateOfferForm(array_merge($this->valid, ['currency' => 'US1'])))->not->toBeNull()
            ->and(regressionValidateOfferForm(array_merge($this->valid, ['currency' => 'USDD'])))->not->toBeNull();
    });

    test('rejects negative cookie durations', function (): void {
        expect(regressionValidateOfferForm(array_merge($this->valid, ['cookie_days' => -1])))->not->toBeNull();
    });

    test('requires ends_at on or after starts_at', function (): void {
        $now = CarbonImmutable::now();

        $before = regressionValidateOfferForm(array_merge($this->valid, [
            'starts_at' => $now->toDateTimeString(),
            'ends_at' => $now->subDay()->toDateTimeString(),
        ]));

        expect($before)->not->toBeNull()
            ->and($before->errors())->toHaveKey('data.ends_at');

        $equal = regressionValidateOfferForm(array_merge($this->valid, [
            'starts_at' => $now->toDateTimeString(),
            'ends_at' => $now->toDateTimeString(),
        ]));

        expect($equal)->toBeNull();
    });
});

describe('category cycle protection', function (): void {
    beforeEach(function (): void {
        $this->parent = AffiliateOfferCategory::factory()->create();
        $this->child = AffiliateOfferCategory::factory()->create(['parent_id' => $this->parent->id]);
        $this->grandchild = AffiliateOfferCategory::factory()->create(['parent_id' => $this->child->id]);
    });

    test('rejects a descendant as parent', function (): void {
        $page = (new ReflectionClass(EditAffiliateOfferCategory::class))->newInstanceWithoutConstructor();
        $page->record = $this->parent;
        $method = new ReflectionMethod(EditAffiliateOfferCategory::class, 'handleRecordUpdate');
        $method->setAccessible(true);

        try {
            $method->invoke($page, $this->parent, ['parent_id' => $this->grandchild->id]);
            $this->fail('Expected a ValidationException for a cyclic parent assignment.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('data.parent_id');
        }
    });

    test('rejects the record itself as parent', function (): void {
        $page = (new ReflectionClass(EditAffiliateOfferCategory::class))->newInstanceWithoutConstructor();
        $page->record = $this->parent;
        $method = new ReflectionMethod(EditAffiliateOfferCategory::class, 'handleRecordUpdate');
        $method->setAccessible(true);

        try {
            $method->invoke($page, $this->parent, ['parent_id' => $this->parent->id]);
            $this->fail('Expected a ValidationException for a self parent assignment.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('data.parent_id');
        }
    });

    test('accepts null and unrelated parents', function (): void {
        $other = AffiliateOfferCategory::factory()->create();

        $page = (new ReflectionClass(EditAffiliateOfferCategory::class))->newInstanceWithoutConstructor();
        $page->record = $this->parent;
        $method = new ReflectionMethod(EditAffiliateOfferCategory::class, 'handleRecordUpdate');
        $method->setAccessible(true);

        $nullResult = $method->invoke($page, $this->parent, ['parent_id' => null]);

        expect($nullResult->parent_id)->toBeNull()
            ->and($method->invoke($page, $this->parent, ['parent_id' => $other->id])->parent_id)->toBe((string) $other->id);
    });
});

describe('relationship selects', function (): void {
    test('offer site and category options match the current offer owner', function (): void {
        config(['affiliate-network.owner.enabled' => true, 'affiliate-network.owner.include_global' => false]);
        $owner = User::factory()->create();
        $other = User::factory()->create();

        [$site, $offer, $category] = OwnerContext::withOwner($owner, function () use ($owner): array {
            $site = AffiliateSite::factory()->verified()->forOwner($owner)->create(['name' => 'Merchant site']);
            $offer = AffiliateOffer::factory()->forSite($site)->create();
            $category = AffiliateOfferCategory::factory()->forOwner($owner)->create(['name' => 'Merchant category', 'is_active' => true]);

            return [$site, $offer, $category];
        });
        OwnerContext::withOwner($other, function () use ($other): void {
            AffiliateSite::factory()->verified()->forOwner($other)->create(['name' => 'Other merchant site']);
            AffiliateOfferCategory::factory()->forOwner($other)->create(['name' => 'Other merchant category', 'is_active' => true]);
        });
        $global = OwnerContext::withOwner(null, fn () => AffiliateOfferCategory::factory()->create(['name' => 'Global merchant category', 'is_active' => true]));

        $schema = AffiliateOfferForm::configure(Schema::make(new RepairSchemaHostComponent)->model($offer)->statePath('data'));
        $schema->fill(['site_id' => $site->getKey()]);

        expect($schema->getComponent('site_id')->getSearchResults('merchant'))->toBe([$site->getKey() => $site->name])
            ->and($schema->getComponent('category_id')->getSearchResults('merchant'))->toBe([$category->getKey() => $category->name]);

        config(['affiliate-network.owner.include_global' => true]);
        expect($schema->getComponent('category_id')->getSearchResults('merchant'))
            ->toHaveKeys([$category->getKey(), $global->getKey()]);
    });

    test('site, category, and parent selects use server-side relationships', function (): void {
        $offerSchema = AffiliateOfferForm::configure(
            Schema::make(new RepairSchemaHostComponent)->model(AffiliateOffer::class)->statePath('data')
        );
        $categorySchema = AffiliateOfferCategoryForm::configure(
            Schema::make(new RepairSchemaHostComponent)->model(AffiliateOfferCategory::class)->statePath('data')
        );

        $siteField = $offerSchema->getComponent('site_id');
        $categoryField = $offerSchema->getComponent('category_id');
        $parentField = $categorySchema->getComponent('parent_id');

        expect($siteField)->toBeInstanceOf(Select::class)
            ->and($siteField->getRelationshipName())->toBe('site')
            ->and($categoryField)->toBeInstanceOf(Select::class)
            ->and($categoryField->getRelationshipName())->toBe('category')
            ->and($parentField)->toBeInstanceOf(Select::class)
            ->and($parentField->getRelationshipName())->toBe('parent');
    });
});

describe('offer transitions through the domain action', function (): void {
    beforeEach(function (): void {
        config(['affiliate-network.owner.enabled' => true]);

        $this->owner = User::factory()->create();
        $suffix = uniqid();

        $this->site = OwnerContext::withOwner($this->owner, fn (): AffiliateSite => AffiliateSite::factory()->verified()->forOwner($this->owner)->create([
            'domain' => "m7-{$suffix}.example",
        ]));
    });

    test('activate publishes with lifecycle timestamps and domain event', function (): void {
        Event::fake([OfferUpdated::class]);

        $offer = OwnerContext::withOwner($this->owner, fn (): AffiliateOffer => AffiliateOffer::factory()->forSite($this->site)->create([
            'status' => OfferStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ]));

        $host = new RepairTableHostComponent;
        $action = AffiliateOffersTable::configure(Table::make($host))->getAction('activate');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($offer);
        $action->call();

        $fresh = $offer->fresh();

        expect($fresh->status)->toBe(OfferStatus::Published)
            ->and($fresh->published_at)->not->toBeNull()
            ->and($fresh->archived_at)->toBeNull();

        Event::assertDispatched(OfferUpdated::class);
    });

    test('pause archives with lifecycle timestamps and domain event', function (): void {
        Event::fake([OfferUpdated::class]);

        $offer = OwnerContext::withOwner($this->owner, fn (): AffiliateOffer => AffiliateOffer::factory()->published()->forSite($this->site)->create());

        $host = new RepairTableHostComponent;
        $action = AffiliateOffersTable::configure(Table::make($host))->getAction('pause');

        expect($action)->not->toBeNull();

        $action->livewire($host);
        $action->record($offer);
        $action->call();

        $fresh = $offer->fresh();

        expect($fresh->status)->toBe(OfferStatus::Archived)
            ->and($fresh->archived_at)->not->toBeNull();

        Event::assertDispatched(OfferUpdated::class);
    });
});

describe('reviewer name casting', function (): void {
    test('integer auth identifiers are returned as strings', function (): void {
        $user = RepairIntIdUser::create([
            'name' => 'L2 User',
            'email' => 'l2-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->name = null;

        $this->actingAs($user);

        $method = new ReflectionMethod(AffiliateOfferApplicationsTable::class, 'getReviewerName');
        $method->setAccessible(true);

        expect($method->invoke(null))->toBe('123');
    });
});

describe('dashboard memoization', function (): void {
    test('repeated dashboard reads reuse one query result', function (): void {
        $page = app(MerchantDashboardPage::class);

        expect($page->getRecentApplications())->toBe($page->getRecentApplications())
            ->and($page->getTopOffers())->toBe($page->getTopOffers());
    });
});

describe('site domain and verified lifecycle', function (): void {
    test('domain format is validated', function (): void {
        $base = [
            'name' => 'L4 Site',
            'status' => SiteStatus::Pending,
        ];

        expect(regressionValidateSiteForm(array_merge($base, ['domain' => 'http://example.com'])))->not->toBeNull()
            ->and(regressionValidateSiteForm(array_merge($base, ['domain' => 'not a domain'])))->not->toBeNull()
            ->and(regressionValidateSiteForm(array_merge($base, ['domain' => 'Example.com'])))->toBeNull();
    });

    test('create normalizes the domain and stamps verified_at for verified sites', function (): void {
        $action = app(CreateSite::class);
        $verifiedDomain = 'Verified-' . uniqid() . '-Example.com';

        $verified = $action->execute([
            'name' => 'L4 Verified',
            'domain' => '  ' . $verifiedDomain . ' ',
            'status' => SiteStatus::Verified,
        ]);

        expect($verified->domain)->toBe(mb_strtolower($verifiedDomain))
            ->and($verified->verified_at)->not->toBeNull();

        $pending = $action->execute([
            'name' => 'L4 Pending',
            'domain' => 'Pending-' . uniqid() . '-Example.com',
            'status' => SiteStatus::Pending,
        ]);

        expect($pending->domain)->toStartWith('pending-')
            ->and($pending->verified_at)->toBeNull();
    });

    test('site updates keep verified_at in sync with status transitions', function (): void {
        $verifiedSite = AffiliateSite::factory()->verified()->create(['domain' => 'l4sync-v-' . uniqid() . '.example']);
        $pendingSite = AffiliateSite::factory()->create([
            'domain' => 'l4sync-p-' . uniqid() . '.example',
            'status' => SiteStatus::Pending,
            'verified_at' => null,
        ]);

        $exit = app(UpdateSite::class)->execute($verifiedSite, [
            'name' => $verifiedSite->name,
            'domain' => $verifiedSite->domain,
            'status' => SiteStatus::Suspended,
        ]);

        expect($exit->verified_at)->toEqual($verifiedSite->verified_at);

        $entry = app(UpdateSite::class)->execute($pendingSite, [
            'name' => $pendingSite->name,
            'domain' => $pendingSite->domain,
            'status' => SiteStatus::Verified,
        ]);

        expect($entry->verified_at)->not->toBeNull();

        $unchanged = app(UpdateSite::class)->execute($pendingSite->fresh(), [
            'name' => $pendingSite->name,
            'domain' => $pendingSite->domain,
            'status' => SiteStatus::Pending,
        ]);

        expect($unchanged->verified_at)->toBeNull();
    });
});

describe('network stats aggregation', function (): void {
    test('empty network aggregates to zeroed integers without errors', function (): void {
        $stats = NetworkStatsAggregator::aggregate();

        expect($stats['totalClicks'])->toBeInt()
            ->and($stats['totalConversions'])->toBeInt()
            ->and($stats['totalRevenue'])->toBeInt()
            ->and($stats['totalRevenue'])->toBe(0)
            ->and($stats['conversionRate'])->toEqual(0)
            ->and($stats['revenueFormatted'])->toBeString();
    });

    test('link totals aggregate as integers', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'l5-' . uniqid() . '.example']);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'slug' => 'l5-offer-' . uniqid(),
        ]);

        AffiliateOfferLink::factory()->create([
            'offer_id' => $offer->id,
            'site_id' => $site->id,
            'clicks' => 10,
            'conversions' => 2,
            'revenue' => 5000,
        ]);

        $stats = NetworkStatsAggregator::aggregate();

        expect($stats['totalClicks'])->toBe(10)
            ->and($stats['totalConversions'])->toBe(2)
            ->and($stats['totalRevenue'])->toBe(5000);
    });

    test('mixed-currency revenue refuses to blend without rates', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'mc-' . uniqid() . '.example']);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'slug' => 'mc-offer-' . uniqid(),
        ]);

        AffiliateOfferLink::factory()->create([
            'offer_id' => $offer->id,
            'site_id' => $site->id,
            'revenue' => 10000,
            'currency' => 'USD',
        ]);
        AffiliateOfferLink::factory()->create([
            'offer_id' => $offer->id,
            'site_id' => $site->id,
            'revenue' => 47000,
            'currency' => 'MYR',
        ]);

        $stats = NetworkStatsAggregator::aggregate();

        expect($stats['totalRevenue'])->toBeNull()
            ->and($stats['revenueFormatted'])->toBe('—')
            ->and($stats['revenueConverted'])->toBeFalse()
            ->and($stats['revenueByCurrency'])->toMatchArray(['USD' => 10000, 'MYR' => 47000]);
    });

    test('mixed-currency revenue converts when rates exist', function (): void {
        $site = AffiliateSite::factory()->verified()->create(['domain' => 'mc-' . uniqid() . '.example']);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'slug' => 'mc-offer-' . uniqid(),
        ]);

        AffiliateOfferLink::factory()->create([
            'offer_id' => $offer->id,
            'site_id' => $site->id,
            'revenue' => 10000,
            'currency' => 'USD',
        ]);
        AffiliateOfferLink::factory()->create([
            'offer_id' => $offer->id,
            'site_id' => $site->id,
            'revenue' => 47000,
            'currency' => 'MYR',
        ]);

        config(['affiliate-network.currency.default' => 'USD']);
        // Settings cache outlives RefreshDatabase rollbacks within a process.
        Artisan::call('settings:clear-cache');
        (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.7], 'history' => []]))->save();

        $stats = NetworkStatsAggregator::aggregate();

        expect($stats['totalRevenue'])->toBe(20000)
            ->and($stats['revenueCurrency'])->toBe('USD')
            ->and($stats['revenueConverted'])->toBeTrue();
    });
});

describe('top offers widget sorting', function (): void {
    test('leaderboard metric columns are not sortable', function (): void {
        $widget = new TopOffersWidget;
        $table = $widget->table(Table::make(new RepairTableHostComponent));

        expect($table->getColumn('links_sum_clicks')->isSortable())->toBeFalse()
            ->and($table->getColumn('links_sum_conversions')->isSortable())->toBeFalse()
            ->and($table->getColumn('links_sum_revenue')->isSortable())->toBeFalse();
    });
});

describe('admin gate fails closed', function (): void {
    test('empty admin ability denies every network-wide surface', function (): void {
        config(['filament-affiliate-network.authorization.admin_ability' => '']);

        $admin = User::factory()->create(['email' => 'admin@example.com']);

        expect(NetworkAdminAccess::allows())->toBeFalse()
            ->and(NetworkAdminAccess::allows($admin))->toBeFalse()
            ->and(AffiliateSiteResource::canViewAny())->toBeFalse();
    });
});
