<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentLinks\Resources\LinkResource;
use AIArmada\FilamentLinks\Resources\LinkResource\Schemas\LinkForm;
use AIArmada\FilamentLinks\Resources\LinkResource\Tables\LinksTable;
use AIArmada\Links\Actions\CreateLink;
use AIArmada\Links\Models\Link;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class LinkResourceHostComponent extends Component implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
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
