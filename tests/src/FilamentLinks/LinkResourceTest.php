<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentLinks\Resources\LinkResource;
use AIArmada\FilamentLinks\Resources\LinkResource\Pages\EditLink;
use AIArmada\FilamentLinks\Resources\LinkResource\Schemas\LinkForm;
use AIArmada\FilamentLinks\Resources\LinkResource\Tables\LinksTable;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Models\Link;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;

class LinkResourceHostComponent extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function table(Table $table): Table
    {
        return LinksTable::configure($table->query(Link::query()));
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

test('link resource reads navigation from config', function (): void {
    expect(LinkResource::getNavigationGroup())->toBe('Links');
    expect(LinkResource::getNavigationSort())->toBe(10);
    expect(array_keys(LinkResource::getPages()))->toBe(['index', 'create', 'edit']);
});

test('link resource queries are owner-scoped', function (): void {
    $ownerA = User::create(['name' => 'Res A', 'email' => 'res-a-' . uniqid() . '@example.com', 'password' => 'secret']);
    $ownerB = User::create(['name' => 'Res B', 'email' => 'res-b-' . uniqid() . '@example.com', 'password' => 'secret']);

    OwnerContext::withOwner($ownerA, fn () => CreateLink::run([
        'name' => 'Scoped link',
        'slug' => 'scoped-link',
        'destination_url' => 'https://merchant.example/scoped',
    ]));

    OwnerContext::withOwner($ownerB, function (): void {
        expect(LinkResource::getEloquentQuery()->count())->toBe(0);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(LinkResource::getEloquentQuery()->count())->toBe(1);
    });
});

test('link form exposes parameters and signature fields', function (): void {
    $schema = LinkForm::configure(
        Schema::make(new LinkResourceHostComponent)->model(Link::class)->statePath('data')
    );

    expect($schema->getComponent('parameters'))->toBeInstanceOf(KeyValue::class)
        ->and($schema->getComponent('require_signature'))->toBeInstanceOf(Toggle::class);
});

test('links table exposes signed and subject columns', function (): void {
    $columns = LinksTable::configure(Table::make(new LinkResourceHostComponent))->getColumns();

    expect($columns)->toHaveKeys(['require_signature', 'subject_type']);
});

test('open action generates a signed url for signed links', function (): void {
    $signed = CreateLink::run([
        'name' => 'Signed row',
        'slug' => 'signed-row',
        'destination_url' => 'https://merchant.example/signed-row',
        'require_signature' => true,
    ]);

    $plain = CreateLink::run([
        'name' => 'Plain row',
        'slug' => 'plain-row',
        'destination_url' => 'https://merchant.example/plain-row',
    ]);

    $table = LinksTable::configure(Table::make(new LinkResourceHostComponent));

    expect($table->getAction('open')->record($signed)->getUrl())->toContain('signature=')
        ->and($table->getAction('open')->record($plain)->getUrl())->toBe($plain->cloakedUrl());
});

test('affiliate tracking links are protected from slug and destination edits and deletion', function (): void {
    config(['filament-links.protected_subject_types' => [AffiliateLink::class]]);
    $link = new Link;
    $link->subject_type = AffiliateLink::class;
    $link->subject_id = (string) Str::uuid();

    expect(LinkForm::isProtectedSubject($link))->toBeTrue()
        ->and(LinkResource::canDelete($link))->toBeFalse();
});

test('affiliate subjects are protected by default and explicit config overrides that default', function (): void {
    config(['filament-links.protected_subject_types' => null]);
    $link = new Link;
    $link->subject_type = AffiliateLink::class;
    $link->subject_id = (string) Str::uuid();

    expect(LinkForm::isProtectedSubject($link))->toBeTrue();

    config(['filament-links.protected_subject_types' => []]);

    expect(LinkForm::isProtectedSubject($link))->toBeFalse();
});

test('deactivate and reactivate actions stay visible only for ordinary links', function (): void {
    config(['filament-links.protected_subject_types' => [AffiliateLink::class]]);

    $ordinaryActive = CreateLink::run([
        'name' => 'Ordinary active link',
        'slug' => 'ordinary-active-link',
        'destination_url' => 'https://merchant.example/ordinary-active',
    ]);
    $ordinaryInactive = CreateLink::run([
        'name' => 'Ordinary inactive link',
        'slug' => 'ordinary-inactive-link',
        'destination_url' => 'https://merchant.example/ordinary-inactive',
    ]);
    $protectedActive = CreateLink::run([
        'name' => 'Protected active link',
        'slug' => 'protected-active-link',
        'destination_url' => 'https://merchant.example/protected-active',
        'subject_type' => AffiliateLink::class,
        'subject_id' => (string) Str::uuid(),
    ]);
    $protectedInactive = CreateLink::run([
        'name' => 'Protected inactive link',
        'slug' => 'protected-inactive-link',
        'destination_url' => 'https://merchant.example/protected-inactive',
        'subject_type' => AffiliateLink::class,
        'subject_id' => (string) Str::uuid(),
    ]);
    DeactivateLink::run($ordinaryInactive);
    DeactivateLink::run($protectedInactive);

    Livewire::test(LinkResourceHostComponent::class)
        ->assertTableActionVisible('deactivate', $ordinaryActive)
        ->assertTableActionHidden('reactivate', $ordinaryActive)
        ->assertTableActionHidden('deactivate', $ordinaryInactive)
        ->assertTableActionVisible('reactivate', $ordinaryInactive)
        ->assertTableActionHidden('deactivate', $protectedActive)
        ->assertTableActionHidden('reactivate', $protectedActive)
        ->assertTableActionHidden('deactivate', $protectedInactive)
        ->assertTableActionHidden('reactivate', $protectedInactive);
});

test('edit page strips protected link parameters, utm defaults, and signature changes', function (): void {
    config(['filament-links.protected_subject_types' => [AffiliateLink::class]]);
    $link = CreateLink::run([
        'name' => 'Affiliate tracking row',
        'slug' => 'affiliate-tracking-row',
        'destination_url' => 'https://merchant.example/original',
        'parameters' => ['aff_link' => 'frozen'],
        'utm_defaults' => ['utm_source' => 'frozen'],
        'require_signature' => true,
        'subject_type' => AffiliateLink::class,
        'subject_id' => (string) Str::uuid(),
    ]);
    $page = new EditLink;
    $method = new ReflectionMethod(EditLink::class, 'handleRecordUpdate');
    $method->setAccessible(true);

    $updated = $method->invoke($page, $link, [
        'name' => 'Renamed affiliate row',
        'slug' => 'tampered-slug',
        'destination_url' => 'https://attacker.example/',
        'parameters' => ['aff_link' => 'forged'],
        'utm_defaults' => ['utm_source' => 'forged'],
        'require_signature' => false,
    ]);

    expect($updated->name)->toBe('Renamed affiliate row')
        ->and($updated->slug)->toBe('affiliate-tracking-row')
        ->and($updated->destination_url)->toBe('https://merchant.example/original')
        ->and($updated->parameters)->toBe(['aff_link' => 'frozen'])
        ->and($updated->utm_defaults)->toBe(['utm_source' => 'frozen'])
        ->and($updated->require_signature)->toBeTrue();
});
