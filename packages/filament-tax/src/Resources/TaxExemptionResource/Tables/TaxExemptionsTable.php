<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxExemptionResource\Tables;

use AIArmada\Tax\Models\TaxExemption;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class TaxExemptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('exemptable.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TaxExemption $record): ?string => $record->exemptable_type === 'AIArmada\\Customers\\Models\\CustomerGroup' ? 'Group' : null),

                TextColumn::make('certificate_number')
                    ->label('Certificate #')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('taxZone.name')
                    ->label('Zone')
                    ->badge()
                    ->placeholder('All Zones')
                    ->color('info'),

                TextColumn::make('starts_at')
                    ->label('Valid From')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Never')
                    ->color(function (TaxExemption $record): string {
                        if (! $record->expires_at) {
                            return 'success';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'danger';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->icon(function (TaxExemption $record): string {
                        if (! $record->expires_at) {
                            return 'heroicon-o-infinity';
                        }

                        if ($record->expires_at->isPast()) {
                            return 'heroicon-o-x-circle';
                        }

                        if ($record->expires_at->isBefore(now()->addDays(30))) {
                            return 'heroicon-o-exclamation-triangle';
                        }

                        return 'heroicon-o-check-circle';
                    }),

                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('expires_at', 'asc')
            ->filters([
                SelectFilter::make('tax_zone_id')
                    ->label('Zone')
                    ->relationship('taxZone', 'name'),

                TernaryFilter::make('is_verified')
                    ->label('Verified'),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                Filter::make('expiring_soon')
                    ->label('Expiring in 30 days')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>=', now())
                            ->where('expires_at', '<=', now()->addDays(30))
                    )
                    ->toggle(),

                Filter::make('expired')
                    ->label('Expired')
                    ->query(
                        fn ($query) => $query
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<', now())
                    )
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('verify')
                        ->label('Verify')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->color('success')
                        ->visible(fn (TaxExemption $record): bool => ! $record->is_verified)
                        ->requiresConfirmation()
                        ->action(fn (TaxExemption $record) => $record->update(['is_verified' => true]))
                        ->successNotificationTitle('Exemption verified'),
                    Action::make('renew')
                        ->label('Renew')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->form([
                            DatePicker::make('new_expires_at')
                                ->label('New Expiry Date')
                                ->required()
                                ->native(false)
                                ->after('today'),
                        ])
                        ->action(function (TaxExemption $record, array $data): void {
                            $record->update(['expires_at' => $data['new_expires_at']]);
                        })
                        ->successNotificationTitle('Exemption renewed'),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('verify')
                    ->label('Verify Selected')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['is_verified' => true]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('deactivate')
                    ->label('Deactivate')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['is_active' => false]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
