<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\FilamentContacting\Schemas\ContactSnapshotInfolistSchema;
use AIArmada\FilamentContacting\Tables\ContactSnapshotTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ContactSnapshotResource extends Resource
{
    protected static ?string $model = ContactSnapshot::class;

    protected static BackedEnum | string | null $navigationIcon = 'heroicon-o-archive-box';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-contacting.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-contacting.navigation.icons.contact_snapshots', parent::getNavigationIcon());
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-contacting.navigation.sort', 70) + 2;
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return ContactSnapshotTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema(ContactSnapshotInfolistSchema::make());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ContactSnapshotResource\Pages\ListContactSnapshots::route('/'),
            'view' => ContactSnapshotResource\Pages\ViewContactSnapshot::route('/{record}'),
        ];
    }
}
