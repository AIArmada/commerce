<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Actions\UpdateAffiliateFraudSignalStatus;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource\Schemas\AffiliateFraudSignalInfolist;
use AIArmada\FilamentAffiliates\Support\AffiliateReferenceProjection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateFraudSignalResource extends Resource
{
    protected static ?string $model = AffiliateFraudSignal::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Fraud Signals';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_fraud_signals', 64);
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

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateFraudSignalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate_name')
                    ->label('Affiliate')
                    ->searchable(query: fn (Builder $query, string $search): Builder => AffiliateReferenceProjection::search($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('affiliate_reference.affiliate_name', $direction)),

                Tables\Columns\TextColumn::make('rule_code')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\BadgeColumn::make('severity')
                    ->colors([
                        'gray' => FraudSeverity::Low->value,
                        'warning' => FraudSeverity::Medium->value,
                        'danger' => fn ($state) => in_array($state, [FraudSeverity::High->value, FraudSeverity::Critical->value]),
                    ]),

                Tables\Columns\TextColumn::make('risk_points')
                    ->label('Risk points')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => FraudSignalStatus::Detected->value,
                        'info' => FraudSignalStatus::Reviewed->value,
                        'gray' => FraudSignalStatus::Dismissed->value,
                        'danger' => FraudSignalStatus::Confirmed->value,
                    ]),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FraudSignalStatus::class),

                Tables\Filters\SelectFilter::make('severity')
                    ->options(FraudSeverity::class),

                Tables\Filters\SelectFilter::make('rule_code')
                    ->options([
                        'CLICK_VELOCITY' => 'Click velocity',
                        'CONVERSION_VELOCITY' => 'Conversion velocity',
                        'GEO_ANOMALY' => 'Rapid IP change',
                        'FAST_CONVERSION' => 'Fast conversion',
                        'FINGERPRINT_REPEAT' => 'Repeated fingerprint',
                        'SELF_REFERRAL' => 'Self referral',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(fn (AffiliateFraudSignal $record): AffiliateFraudSignal => UpdateAffiliateFraudSignalStatus::run(
                        $record,
                        FraudSignalStatus::Dismissed,
                    )),
                Action::make('confirm')
                    ->icon('heroicon-o-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(fn (AffiliateFraudSignal $record): AffiliateFraudSignal => UpdateAffiliateFraudSignalStatus::run(
                        $record,
                        FraudSignalStatus::Confirmed,
                    )),
            ])
            ->bulkActions([
                BulkAction::make('dismiss_selected')
                    ->label('Dismiss Selected')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->action(function ($records): void {
                        $records->each(fn (AffiliateFraudSignal $record) => UpdateAffiliateFraudSignalStatus::run(
                            $record,
                            FraudSignalStatus::Dismissed,
                        ));
                    }),
            ])
            ->defaultSort('detected_at', 'desc');
    }

    /**
     * @return Builder<AffiliateFraudSignal>
     */
    public static function getEloquentQuery(): Builder
    {
        return AffiliateReferenceProjection::apply(parent::getEloquentQuery(), includeGlobal: true);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => AffiliateFraudSignalResource\Pages\ListAffiliateFraudSignals::route('/'),
            'view' => AffiliateFraudSignalResource\Pages\ViewAffiliateFraudSignal::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false)
            ? OwnerContext::resolve()
            : null;

        $count = (int) OwnerCache::remember(
            $owner,
            'filament-affiliates.fraud-signals.detected-count',
            30,
            static function (): int {
                return self::getEloquentQuery()->where('status', FraudSignalStatus::Detected)->count();
            },
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
