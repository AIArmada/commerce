<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaImporter;
use AIArmada\Addressing\Contracts\CountryDatasetSeeder;
use AIArmada\Addressing\Contracts\ResolutionGapReporter;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Features\Geography\Models\AddressAreaName;
use AIArmada\Addressing\Features\ResolutionGaps\Models\ResolutionGap;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAddressing\Persistence\AddressingFilterOptions;
use AIArmada\FilamentAddressing\Resources\ResolutionGapResource;
use AIArmada\FilamentAddressing\Tables\ResolutionGapTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(CountryDatasetSeeder::class)->execute();

    app(AddressAreaImporter::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Wilayah Persekutuan Kuala Lumpur'),
        new AddressAreaData(source: 'areas', sourceId: 'pj', countryCode: 'MY', type: 'locality', level: 2, name: 'Petaling Jaya'),
        new AddressAreaData(source: 'areas', sourceId: 'district', countryCode: 'MY', type: 'district', level: 2, name: 'Petaling'),
    ]));

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

final class CustomResolutionGapResourceModel extends ResolutionGap {}

function logTestGap(string $value = 'Kuala Lumpur', string $role = 'postal_locality', string $reason = 'unmatched'): ResolutionGap
{
    return ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', $role, $value, $reason));
}

it('registers the resolution gap resource with the configured model', function (): void {
    expect(config('filament-addressing.resources.resolution_gaps.enabled'))->toBeTrue()
        ->and(ResolutionGapResource::getModel())->toBe(ResolutionGap::class)
        ->and(ResolutionGapResource::isReadOnly())->toBeFalse()
        ->and(ResolutionGapResource::getNavigationSort())->toBe(84)
        ->and(ResolutionGapResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(ResolutionGapResource::getPages())->not->toHaveKeys(['create', 'edit']);
});

it('hides mutating gap actions when read-only or the feature flag is off', function (): void {
    $gap = logTestGap();

    expect(ResolutionGapTable::mutatingActionsEnabled())->toBeTrue()
        ->and(ResolutionGapTable::matchActionVisible($gap))->toBeTrue()
        ->and(ResolutionGapTable::ignoreActionVisible($gap))->toBeTrue();

    config()->set('filament-addressing.resources.resolution_gaps.read_only', true);

    expect(ResolutionGapTable::mutatingActionsEnabled())->toBeFalse()
        ->and(ResolutionGapTable::matchActionVisible($gap))->toBeFalse()
        ->and(ResolutionGapTable::ignoreActionVisible($gap))->toBeFalse();

    config()->set('filament-addressing.resources.resolution_gaps.read_only', false);
    config()->set('filament-addressing.features.gap_actions', false);

    expect(ResolutionGapTable::mutatingActionsEnabled())->toBeFalse()
        ->and(ResolutionGapTable::matchActionVisible($gap))->toBeFalse()
        ->and(ResolutionGapTable::ignoreActionVisible($gap))->toBeFalse();
});

it('disables matching for ambiguous and state gaps with an explanation', function (): void {
    $ambiguous = logTestGap('Springfield', 'postal_locality', 'ambiguous');
    $state = logTestGap('Johor', 'state');
    $custom = logTestGap('Shelbyville', 'postal_locality', 'timeout');

    expect(ResolutionGapTable::matchDisabledReason($ambiguous))->toContain('data cleanup')
        ->and(ResolutionGapTable::matchDisabledReason($state))->toContain('no alias table')
        ->and(ResolutionGapTable::matchDisabledReason($custom))->toContain('Only gaps with an unmatched reason')
        ->and(ResolutionGapTable::matchDisabledReason(logTestGap()))->toBeNull();
});

it('hides match once resolved and ignore once ignored', function (): void {
    $matched = logTestGap();
    $matched->status = 'matched';
    $ignored = logTestGap('junk');
    $ignored->status = 'ignored';

    expect(ResolutionGapTable::matchActionVisible($matched))->toBeFalse()
        ->and(ResolutionGapTable::ignoreActionVisible($matched))->toBeTrue()
        ->and(ResolutionGapTable::matchActionVisible($ignored))->toBeFalse()
        ->and(ResolutionGapTable::ignoreActionVisible($ignored))->toBeFalse();
});

it('matches a gap through the core action', function (): void {
    $gap = logTestGap();

    ResolutionGapTable::runMatch($gap, ['address_area_id' => $this->areas['kl']->getKey()]);

    expect($gap->fresh()?->status)->toBe('matched')
        ->and($gap->fresh()?->matched_area_id)->toBe($this->areas['kl']->getKey())
        ->and(AddressAreaName::query()->where('address_area_id', $this->areas['kl']->getKey())->where('name', 'Kuala Lumpur')->exists())->toBeTrue();
});

it('surfaces core match failures', function (): void {
    $gap = logTestGap('Petaling');

    expect(fn (): mixed => ResolutionGapTable::runMatch($gap, ['address_area_id' => $this->areas['district']->getKey()]))
        ->toThrow(ValidationException::class, 'does not match the required hierarchy level')
        ->and($gap->fresh()?->status)->toBe('open');
});

it('ignores gaps through the core action including bulk', function (): void {
    $single = logTestGap('junk one');
    $bulkOne = logTestGap('junk two');
    $bulkTwo = logTestGap('junk three');
    $bulkTwo->status = 'ignored';
    $bulkTwo->save();

    ResolutionGapTable::runIgnore($single);
    ResolutionGapTable::runBulkIgnore(ResolutionGap::query()->whereIn('id', [$bulkOne->getKey(), $bulkTwo->getKey()])->get());

    expect($single->fresh()?->status)->toBe('ignored')
        ->and($bulkOne->fresh()?->status)->toBe('ignored')
        ->and($bulkTwo->fresh()?->status)->toBe('ignored');
});

it('searches areas scoped to the gap country and role definition', function (): void {
    $gap = logTestGap();

    $results = ResolutionGapTable::searchAreas('petaling', $gap);

    expect($results)->toHaveCount(1)
        ->and($results[$this->areas['pj']->getKey()])->toContain('Petaling Jaya')
        ->and($results[$this->areas['pj']->getKey()])->toContain('locality')
        ->and($results[$this->areas['pj']->getKey()])->toContain('level 2');
});

it('returns no area options for gaps without a profile definition', function (): void {
    $gap = logTestGap('Johor', 'state');

    expect(ResolutionGapTable::searchAreas('johor', $gap))->toBe([]);
});

it('lists gap columns, filters, and row actions', function (): void {
    $table = ResolutionGapTable::make(Table::make(Mockery::mock(HasTable::class)));
    $actionNames = collect($table->getRecordActions())->map(fn ($action): string => $action->getName())->all();

    expect(array_key_first($table->getColumns()))->toBe('value')
        ->and($table->getColumns())->toHaveKeys(['value', 'country_code', 'role', 'reason', 'status', 'hits', 'last_seen_at'])
        ->and($table->getFilters())->toHaveKeys(['country_code', 'role', 'reason', 'status'])
        ->and($actionNames)->toContain('match', 'ignore');
});

it('maps distinct gap roles for the role filter', function (): void {
    logTestGap('Kuala Lumpur', 'postal_locality');
    logTestGap('Johor', 'state');

    expect(AddressingFilterOptions::gapRoles())->toBe([
        'postal_locality' => 'Postal locality',
        'state' => 'State',
    ]);
});

it('scopes cached gap roles per owner with global roles shared', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): ResolutionGap => logTestGap('Alpha Value', 'alpha_probe'));
    OwnerContext::withOwner($ownerB, fn (): ResolutionGap => logTestGap('Beta Value', 'beta_probe'));
    OwnerContext::withOwner(null, fn (): ResolutionGap => logTestGap('Global Value', 'global_probe'));

    $rolesA = OwnerContext::withOwner($ownerA, fn (): array => AddressingFilterOptions::gapRoles());
    $rolesB = OwnerContext::withOwner($ownerB, fn (): array => AddressingFilterOptions::gapRoles());

    expect($rolesA)->toBe(['alpha_probe' => 'Alpha probe', 'global_probe' => 'Global probe'])
        ->and($rolesB)->toBe(['beta_probe' => 'Beta probe', 'global_probe' => 'Global probe']);
});

it('uses the feature configured model when no Filament-specific override is present', function (): void {
    config()->set('addressing.models.resolution_gap', CustomResolutionGapResourceModel::class);
    config()->set('filament-addressing.resources.resolution_gaps.model', null);

    expect(ResolutionGapResource::getModel())->toBe(CustomResolutionGapResourceModel::class);

    $id = app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Configured model');

    expect(CustomResolutionGapResourceModel::query()->findOrFail($id))->toBeInstanceOf(CustomResolutionGapResourceModel::class);
});
