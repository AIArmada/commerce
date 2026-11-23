<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Features\Attribution\Actions\AssignOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RevokeOfferCoupon;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferCoupon;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CouponsRelationManager extends RelationManager
{
    protected static string $relationship = 'coupons';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class))
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Coupon')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('affiliate_id')
                    ->label('Affiliate ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('site_id')
                    ->label('Site')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('starts_at')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->dateTime()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                Action::make('assign_coupon')
                    ->label('Assign coupon')
                    ->icon('heroicon-o-ticket')
                    ->color('primary')
                    ->form([
                        TextInput::make('affiliate_id')
                            ->label('Affiliate ID')
                            ->required()
                            ->maxLength(36),
                        TextInput::make('code')
                            ->label('Coupon code')
                            ->required()
                            ->maxLength(64),
                        DateTimePicker::make('starts_at')
                            ->nullable(),
                        DateTimePicker::make('ends_at')
                            ->nullable(),
                    ])
                    ->action(function (array $data): void {
                        $startsAt = is_string($data['starts_at'] ?? null) && $data['starts_at'] !== ''
                            ? CarbonImmutable::parse($data['starts_at'])
                            : null;
                        $endsAt = is_string($data['ends_at'] ?? null) && $data['ends_at'] !== ''
                            ? CarbonImmutable::parse($data['ends_at'])
                            : null;

                        app(AssignOfferCoupon::class)->execute(
                            (string) $this->getOwnerRecord()->getKey(),
                            (string) $data['affiliate_id'],
                            (string) $data['code'],
                            $startsAt,
                            $endsAt,
                        );
                    }),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateOfferCoupon $record): bool => (bool) $record->is_active)
                    ->action(function (AffiliateOfferCoupon $record): void {
                        app(RevokeOfferCoupon::class)->execute((string) $record->getKey());
                    }),
            ])
            ->bulkActions([]);
    }
}
