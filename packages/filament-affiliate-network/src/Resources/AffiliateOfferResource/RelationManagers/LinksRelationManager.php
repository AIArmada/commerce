<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Services\NetworkLedgerReconciliationService;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only link ledger with ledger reconciliation.
 *
 * Links are issued by the application flow, so operators cannot create or
 * edit them here — but they can prove every counted conversion posted to
 * the affiliates ledger exactly once.
 */
final class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('link.slug')
            ->columns([
                TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('link.slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('clicks')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('conversions')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('revenue')
                    ->money(fn (AffiliateOfferLink $record): string => mb_strtoupper((string) ($record->currency ?? config('affiliate-network.currency.default', 'MYR'))))
                    ->sortable(),

                TextColumn::make('currency')
                    ->badge(),
            ])
            ->headerActions([
                Action::make('reconcile_offer')
                    ->label('Reconcile offer')
                    ->icon('heroicon-o-scale')
                    ->color('info')
                    ->modalHeading('Ledger reconciliation')
                    ->modalDescription(function (): string {
                        $ownerRecord = $this->getOwnerRecord();
                        $report = $ownerRecord instanceof AffiliateOffer
                            ? app(NetworkLedgerReconciliationService::class)->reconcileOffer($ownerRecord)
                            : ['match' => false, 'links' => 0, 'matched_links' => 0];

                        if ($report['match']) {
                            return sprintf(
                                'All %d link(s) match: every counted conversion posted exactly once.',
                                $report['links'],
                            );
                        }

                        return sprintf(
                            '%d of %d link(s) match. Open a link row to inspect the differences.',
                            $report['matched_links'],
                            $report['links'],
                        );
                    })
                    ->modalSubmitAction(false)
                    ->action(fn (): null => null),
            ])
            ->actions([
                Action::make('reconcile')
                    ->label('Reconcile')
                    ->icon('heroicon-o-scale')
                    ->color('gray')
                    ->modalHeading(fn (AffiliateOfferLink $record): string => 'Reconcile link ' . $record->link?->slug)
                    ->modalDescription(function (AffiliateOfferLink $record): string {
                        $report = app(NetworkLedgerReconciliationService::class)->reconcileLink($record);

                        if ($report['match']) {
                            return sprintf(
                                'Match: network counts %d conversion(s) / %d minor and the ledger agrees.',
                                $report['network']['conversions'],
                                $report['network']['revenue_minor'],
                            );
                        }

                        return 'Mismatch: ' . implode('; ', $report['differences']);
                    })
                    ->modalSubmitAction(false)
                    ->action(fn (): null => null),
            ]);
    }
}
