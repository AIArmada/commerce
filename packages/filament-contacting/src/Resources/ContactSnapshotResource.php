<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Resources;

use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\FilamentContacting\Schemas\ContactSnapshotInfolistSchema;
use AIArmada\FilamentContacting\Tables\ContactSnapshotTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ContactSnapshotResource extends Resource
{
    protected static ?string $model = ContactSnapshot::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-contacting.navigation.group');
    }

    public static function getNavigationIcon(): string
    {
        return (string) config('filament-contacting.navigation.icons.contact_snapshots', parent::getNavigationIcon());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return ContactSnapshotTable::table($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema(ContactSnapshotInfolistSchema::make());
    }

    public static function getPages(): array
    {
        return [
            'index' => ContactSnapshotResource\Pages\ListContactSnapshots::route('/'),
            'view' => ContactSnapshotResource\Pages\ViewContactSnapshot::route('/{record}'),
        ];
    }
}
