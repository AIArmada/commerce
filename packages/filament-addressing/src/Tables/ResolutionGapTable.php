<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Actions\IgnoreResolutionGapAction;
use AIArmada\Addressing\Actions\MatchGapToAreaAction;
use AIArmada\Addressing\Data\AddressLevelDefinition;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Resources\ResolutionGapResource;
use AIArmada\FilamentAddressing\Rules\AddressAreasBelongToCountry;
use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final class ResolutionGapTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['matchedArea']))
            ->columns([
                TextColumn::make('value')
                    ->searchable()
                    ->sortable()
                    ->url(fn (ResolutionGap $record): string => ResolutionGapResource::getUrl('view', ['record' => $record])),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('hits')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('matchedArea.name')
                    ->label('Matched area')
                    ->url(fn (ResolutionGap $record): ?string => $record->matchedArea
                        ? AddressAreaResource::getUrl('view', ['record' => $record->matchedArea])
                        : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(fn (): array => AddressingFilterOptions::countryOptions())
                    ->searchable(),
                SelectFilter::make('role')
                    ->options(fn (): array => AddressingFilterOptions::gapRoles())
                    ->searchable(),
                SelectFilter::make('reason')
                    ->options([
                        'unmatched' => 'Unmatched',
                        'ambiguous' => 'Ambiguous',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'matched' => 'Matched',
                        'ignored' => 'Ignored',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('match')
                    ->label('Match to area')
                    ->icon('heroicon-o-link')
                    ->visible(fn (ResolutionGap $record): bool => self::matchActionVisible($record))
                    ->disabled(fn (ResolutionGap $record): bool => self::matchDisabledReason($record) !== null)
                    ->tooltip(fn (ResolutionGap $record): ?string => self::matchDisabledReason($record))
                    ->form(fn (ResolutionGap $record): array => [
                        Placeholder::make('evidence')
                            ->label('Gap evidence')
                            ->content(self::evidenceSummary($record)),
                        Select::make('address_area_id')
                            ->label('Area')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::searchAreas($search, $record))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => self::areaOptionLabel($value))
                            ->noSearchResultsMessage('No matching area found. If the area is missing, create it in the Areas resource first, then match.')
                            ->rules([new AddressAreasBelongToCountry($record->country_code)]),
                    ])
                    ->action(function (ResolutionGap $record, array $data): void {
                        self::runMatch($record, $data);
                    }),
                Actions\Action::make('ignore')
                    ->label('Ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (ResolutionGap $record): bool => self::ignoreActionVisible($record))
                    ->action(function (ResolutionGap $record): void {
                        self::runIgnore($record);
                    }),
            ])
            ->toolbarActions([
                Actions\BulkAction::make('ignore_selected')
                    ->label('Ignore selected')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => self::mutatingActionsEnabled())
                    ->action(function (Collection $records): void {
                        self::runBulkIgnore($records);
                    }),
            ])
            ->defaultSort('hits', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function mutatingActionsEnabled(): bool
    {
        return ! ResolutionGapResource::isReadOnly()
            && (bool) config('filament-addressing.features.gap_actions', true);
    }

    public static function matchActionVisible(ResolutionGap $record): bool
    {
        return self::mutatingActionsEnabled() && $record->status === 'open';
    }

    public static function matchDisabledReason(ResolutionGap $record): ?string
    {
        if ($record->reason !== 'unmatched') {
            return $record->reason === 'ambiguous'
                ? 'Ambiguous gaps need data cleanup before they can be matched to an area.'
                : 'Only gaps with an unmatched reason can be matched to an area.';
        }

        if ($record->role === 'state') {
            return 'States have no alias table. Fix the integration prefix rules or rename the provider state instead.';
        }

        return null;
    }

    public static function ignoreActionVisible(ResolutionGap $record): bool
    {
        return self::mutatingActionsEnabled() && $record->status !== 'ignored';
    }

    /** @return array<string, string> */
    public static function searchAreas(string $search, ResolutionGap $record): array
    {
        $search = mb_trim($search);

        if ($search === '') {
            return [];
        }

        $definition = app(CountryAddressProfileResolver::class)->definitionForRole($record->country_code, (string) $record->role);

        if ($definition === null || $definition['level']->kind !== 'area') {
            return [];
        }

        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
        $needle = LikeSearch::contains($search);

        return $areaClass::query()
            ->where('country_code', mb_strtoupper(mb_trim((string) $record->country_code)))
            ->where('is_active', true)
            ->when(self::areaTypes($definition['level']) !== [], fn (Builder $query): Builder => $query->whereIn('type', self::areaTypes($definition['level'])))
            ->when(self::areaLevels($definition['level']) !== [], fn (Builder $query): Builder => $query->whereIn('level', self::areaLevels($definition['level'])))
            ->where(function (Builder $searchQuery) use ($needle): void {
                LikeSearch::whereLike($searchQuery, 'name', $needle);
                LikeSearch::orWhereLike($searchQuery, 'slug', $needle);
                LikeSearch::orWhereLike($searchQuery, 'code', $needle);
            })
            ->with('parent')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->mapWithKeys(static fn (AddressArea $area): array => [(string) $area->getKey() => self::areaLabel($area)])
            ->toArray();
    }

    public static function runMatch(ResolutionGap $record, array $data): void
    {
        $areaId = $data['address_area_id'] ?? null;

        if (! is_string($areaId) || mb_trim($areaId) === '') {
            throw new LogicException('An area must be selected to match a resolution gap.');
        }

        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
        $area = $areaClass::query()->whereKey(mb_trim($areaId))->firstOrFail();

        app(MatchGapToAreaAction::class)->execute($record, $area, self::actor());

        Notification::make()
            ->title('Gap matched')
            ->success()
            ->send();
    }

    public static function runIgnore(ResolutionGap $record): void
    {
        app(IgnoreResolutionGapAction::class)->execute($record);

        Notification::make()
            ->title('Gap ignored')
            ->success()
            ->send();
    }

    public static function runBulkIgnore(Collection $records): void
    {
        $ignored = 0;

        foreach ($records as $record) {
            if (! $record instanceof ResolutionGap || $record->status === 'ignored') {
                continue;
            }

            app(IgnoreResolutionGapAction::class)->execute($record);
            $ignored++;
        }

        Notification::make()
            ->title("{$ignored} gaps ignored")
            ->success()
            ->send();
    }

    private static function areaOptionLabel(mixed $value): ?string
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
        $area = $areaClass::query()->with('parent')->whereKey(mb_trim($value))->first();

        return $area instanceof AddressArea ? self::areaLabel($area) : null;
    }

    private static function areaLabel(AddressArea $area): string
    {
        $label = sprintf('%s — %s', (string) $area->name, (string) $area->type);

        if ($area->level !== null) {
            $label .= sprintf(' · level %d', (int) $area->level);
        }

        if ($area->parent instanceof AddressArea) {
            $label .= sprintf(' · under %s', (string) $area->parent->name);
        }

        return $label;
    }

    private static function evidenceSummary(ResolutionGap $record): string
    {
        $lines = [
            sprintf('Value: %s', (string) $record->value),
            sprintf('Source: %s · Country: %s · Role: %s', (string) $record->source, (string) $record->country_code, (string) $record->role),
            sprintf('Hits: %d · Reason: %s', (int) $record->hits, (string) $record->reason),
        ];

        if (is_array($record->context) && $record->context !== []) {
            $lines[] = 'Sample: ' . json_encode($record->context);
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private static function areaTypes(AddressLevelDefinition $level): array
    {
        return $level->areaTypes !== []
            ? $level->areaTypes
            : ($level->areaType !== null ? [$level->areaType] : []);
    }

    /** @return list<int> */
    private static function areaLevels(AddressLevelDefinition $level): array
    {
        return $level->areaLevels !== []
            ? $level->areaLevels
            : ($level->areaLevel !== null ? [$level->areaLevel] : []);
    }

    private static function actor(): ?string
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $email = $user->getAttribute('email');

        return is_string($email) && mb_trim($email) !== '' ? $email : (string) $user->getKey();
    }
}
