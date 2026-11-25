<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\UpsertOfferDeal;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferDeal;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DealsRelationManager extends RelationManager
{
    protected static string $relationship = 'deals';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class))
            ->recordTitleAttribute('affiliate_id')
            ->columns([
                TextColumn::make('affiliate_id')
                    ->label('Affiliate ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('rate_base_bp')
                    ->label('Base rate (bp)')
                    ->numeric()
                    ->placeholder('inherits'),

                TextColumn::make('rate_fixed_minor')
                    ->label('Fixed (minor)')
                    ->numeric()
                    ->placeholder('inherits'),

                TextColumn::make('network_fee_bp')
                    ->label('Fee (bp)')
                    ->numeric()
                    ->placeholder('inherits'),

                TextColumn::make('currency')
                    ->badge(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('upsert_deal')
                    ->label('Upsert deal')
                    ->icon('heroicon-o-hand-raised')
                    ->color('primary')
                    ->form([
                        TextInput::make('affiliate_id')
                            ->label('Affiliate ID')
                            ->required()
                            ->maxLength(36),
                        TextInput::make('rate_base_bp')
                            ->label('Base rate (bp)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),
                        TextInput::make('rate_fixed_minor')
                            ->label('Fixed (minor)')
                            ->rule('integer')
                            ->minValue(0)
                            ->nullable(),
                        TextInput::make('network_fee_bp')
                            ->label('Network fee (bp)')
                            ->rule('integer')
                            ->minValue(0)
                            ->maxValue(10000)
                            ->nullable(),
                        TextInput::make('currency')
                            ->length(3)
                            ->alpha()
                            ->nullable(),
                    ])
                    ->action(function (array $data): void {
                        $affiliateId = (string) $data['affiliate_id'];

                        app(UpsertOfferDeal::class)->execute(
                            (string) $this->getOwnerRecord()->getKey(),
                            $affiliateId,
                            array_filter([
                                'rate_base_bp' => $data['rate_base_bp'] ?? null,
                                'rate_fixed_minor' => $data['rate_fixed_minor'] ?? null,
                                'network_fee_bp' => $data['network_fee_bp'] ?? null,
                                'currency' => $data['currency'] ?? null,
                            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                        );
                    }),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOfferDeal $record): bool => (bool) $record->is_active)
                    ->action(function (AffiliateOfferDeal $record): void {
                        app(RevokeOfferDeal::class)->execute($record);
                    }),
            ])
            ->bulkActions([]);
    }
}
