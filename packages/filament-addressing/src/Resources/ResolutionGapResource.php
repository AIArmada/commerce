<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources;

use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\FilamentAddressing\Resources\ResolutionGapResource\Pages\ListResolutionGaps;
use AIArmada\FilamentAddressing\Resources\ResolutionGapResource\Pages\ViewResolutionGap;
use AIArmada\FilamentAddressing\Tables\ResolutionGapTable;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

final class ResolutionGapResource extends Resource
{
    protected static ?string $slug = 'resolution-gaps';

    protected static ?string $model = ResolutionGap::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-circle';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group');
    }

    public static function getNavigationIcon(): BackedEnum | string | null
    {
        return config('filament-addressing.navigation.icons.resolution_gaps', parent::getNavigationIcon());
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-addressing.navigation.sort', 80) + 4;
    }

    public static function getModel(): string
    {
        return config('filament-addressing.resources.resolution_gaps.model', ResolutionGap::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-addressing.navigation.enabled', true);
    }

    public static function table(Table $table): Table
    {
        return ResolutionGapTable::make($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Gap')
                ->schema([
                    TextEntry::make('value'),
                    TextEntry::make('source'),
                    TextEntry::make('country_code')->badge(),
                    TextEntry::make('role')->badge(),
                    TextEntry::make('reason')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('hits'),
                    TextEntry::make('first_seen_at')->dateTime(),
                    TextEntry::make('last_seen_at')->dateTime(),
                ])->columns(3),
            Section::make('Match')
                ->schema([
                    TextEntry::make('matchedArea.name')
                        ->label('Matched area')
                        ->url(fn (ResolutionGap $record): ?string => $record->matchedArea
                            ? AddressAreaResource::getUrl('view', ['record' => $record->matchedArea])
                            : null),
                    TextEntry::make('matched_by'),
                    TextEntry::make('matched_at')->dateTime(),
                ])->columns(3),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResolutionGaps::route('/'),
            'view' => ViewResolutionGap::route('/{record}'),
        ];
    }

    public static function isReadOnly(): bool
    {
        return (bool) config('filament-addressing.resources.resolution_gaps.read_only', false);
    }
}
