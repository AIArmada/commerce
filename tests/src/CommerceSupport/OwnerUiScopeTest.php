<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (app()->bound(OwnerResolverInterface::class)) {
        app()->forgetInstance(OwnerResolverInterface::class);
        app()->offsetUnset(OwnerResolverInterface::class);
    }

    Schema::dropIfExists('owner_ui_scope_fixtures');
    Schema::create('owner_ui_scope_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

it('applies owner ui scoping for owner and explicit global contexts', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-ui-scope-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-ui-scope-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a',
    ]));

    OwnerContext::withOwner($ownerB, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-b',
    ]));

    OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'global',
    ]));

    $ownerLabels = OwnerContext::withOwner($ownerA, fn (): array => OwnerUiScope::apply(OwnerUiScopeFixture::query(), includeGlobal: true)
        ->orderBy('label')
        ->pluck('label')
        ->all());

    $globalLabels = OwnerContext::withOwner(null, fn (): array => OwnerUiScope::apply(OwnerUiScopeFixture::query())
        ->orderBy('label')
        ->pluck('label')
        ->all());

    expect($ownerLabels)->toEqual(['global', 'owner-a'])
        ->and($globalLabels)->toEqual(['global']);
});

it('distinguishes access from mutation for global rows and fails closed for foreign record helpers', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-ui-scope-access-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-ui-scope-access-b@example.com',
        'password' => 'secret',
    ]);

    $ownerAPrimary = OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a-primary',
    ]));

    $ownerASecondary = OwnerContext::withOwner($ownerA, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-a-secondary',
    ]));

    $ownerBRecord = OwnerContext::withOwner($ownerB, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'owner-b',
    ]));

    $globalRecord = OwnerContext::withOwner(null, fn (): Model => OwnerUiScopeFixture::query()->create([
        'label' => 'global',
    ]))->refresh();

    OwnerContext::withOwner($ownerA, function () use ($globalRecord, $ownerAPrimary, $ownerASecondary, $ownerBRecord): void {
        expect(OwnerUiScope::canAccessRecord($globalRecord, includeGlobal: true))->toBeTrue()
            ->and(OwnerUiScope::canMutateRecord($globalRecord))->toBeFalse()
            ->and(OwnerUiScope::findForRecordOwner(OwnerUiScopeFixture::class, $ownerAPrimary, $ownerASecondary->getKey())?->getKey())
            ->toBe($ownerASecondary->getKey())
            ->and(OwnerUiScope::applyForRecordOwner(OwnerUiScopeFixture::query(), $ownerBRecord)->count())
            ->toBe(0);
    });
});

final class OwnerUiScopeFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_ui_scope_fixtures';
    }
}
