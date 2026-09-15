<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Tables;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Ticketing\Enums\TicketAccessType;
use AIArmada\Ticketing\Enums\TicketTypeStatus;
use AIArmada\Ticketing\Enums\TicketTypeVisibility;
use AIArmada\Ticketing\Models\TicketType;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;

/**
 * Shared ticket-type table columns and filters, used by the resource and relation managers alike.
 */
final class TicketTypeTable
{
    /**
     * @return array<int, Column>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('code')
                ->badge()
                ->searchable(),
            TextColumn::make('access_type')
                ->badge()
                ->color(fn (string $state): string => TicketAccessType::tryFrom($state)?->color() ?? 'gray'),
            TextColumn::make('price')
                ->formatStateUsing(
                    static fn (?int $state, TicketType $record): string => $state === null
                        ? '—'
                        : MoneyFormatter::formatMinor($state, $record->currency ?? 'MYR')
                )
                ->alignEnd(),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => TicketTypeStatus::tryFrom($state)?->color() ?? 'gray'),
            TextColumn::make('visibility')
                ->badge()
                ->color(fn (TicketTypeVisibility | string $state): string => match ($state instanceof TicketTypeVisibility ? $state->value : $state) {
                    'public' => 'success',
                    'private' => 'warning',
                    'hidden' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('sales_starts_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('sales_ends_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->options(TicketTypeStatus::options()),
            SelectFilter::make('access_type')
                ->options(TicketAccessType::options()),
            SelectFilter::make('visibility')
                ->options(TicketTypeVisibility::options()),
        ];
    }
}
