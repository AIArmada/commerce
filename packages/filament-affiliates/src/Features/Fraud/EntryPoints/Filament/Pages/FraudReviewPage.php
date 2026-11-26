<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Actions\BulkFraudReviewAction;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Actions\UpdateAffiliateFraudSignalStatus;
use AIArmada\FilamentAffiliates\Support\AffiliateReferenceProjection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class FraudReviewPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Fraud Review';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.pages.navigation_sort.fraud_review', 15);
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.fraud-review';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateReferenceProjection::apply(AffiliateFraudSignal::query(), includeGlobal: true)
                    ->where('status', FraudSignalStatus::Detected)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate_code')
                    ->label('Affiliate')
                    ->searchable(query: fn (Builder $query, string $search): Builder => AffiliateReferenceProjection::search($query, $search, byCode: true))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('affiliate_reference.affiliate_code', $direction)),

                Tables\Columns\TextColumn::make('rule_code')
                    ->label('Signal')
                    ->badge()
                    ->color(fn ($state): string => match ((string) $state) {
                        'CLICK_VELOCITY', 'CONVERSION_VELOCITY', 'SELF_REFERRAL' => 'danger',
                        'GEO_ANOMALY', 'FAST_CONVERSION', 'FINGERPRINT_REPEAT' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof FraudSeverity ? $state->value : (string) $state) {
                        FraudSeverity::Critical->value => 'danger',
                        FraudSeverity::High->value => 'warning',
                        FraudSeverity::Medium->value => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('risk_points')
                    ->label('Risk points'),

                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_code')
                    ->options([
                        'CLICK_VELOCITY' => 'Click velocity',
                        'CONVERSION_VELOCITY' => 'Conversion velocity',
                        'GEO_ANOMALY' => 'Rapid IP change',
                        'FAST_CONVERSION' => 'Fast conversion',
                        'FINGERPRINT_REPEAT' => 'Repeated fingerprint',
                        'SELF_REFERRAL' => 'Self referral',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'critical' => 'Critical',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->action(fn (AffiliateFraudSignal $record): AffiliateFraudSignal => UpdateAffiliateFraudSignalStatus::run(
                        $record,
                        FraudSignalStatus::Dismissed,
                    )),

                Action::make('reject')
                    ->label('Confirm Fraud')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Review Notes')
                            ->required(),
                    ])
                    ->action(fn (AffiliateFraudSignal $record, array $data): AffiliateFraudSignal => UpdateAffiliateFraudSignalStatus::run(
                        $record,
                        FraudSignalStatus::Confirmed,
                        $data['notes'] ?? null,
                        true,
                    )),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->action(function ($records): void {
                        $records->each(fn ($record) => UpdateAffiliateFraudSignalStatus::run(
                            $record,
                            FraudSignalStatus::Dismissed,
                        ));
                    }),

                BulkAction::make('bulk_reject')
                    ->label('Confirm Fraud (Selected)')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->action(function ($records): void {
                        $records->each(fn ($record) => UpdateAffiliateFraudSignalStatus::run(
                            $record,
                            FraudSignalStatus::Confirmed,
                            null,
                            true,
                        ));
                    }),

                BulkFraudReviewAction::make('bulk_fraud_review')
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update'])),
            ]);
    }

    public function getViewData(): array
    {
        $base = AffiliateFraudSignal::query()
            ->where('status', FraudSignalStatus::Detected);

        return [
            'pendingCount' => (clone $base)->count(),
            'criticalCount' => (clone $base)
                ->where('severity', 'critical')
                ->count(),
        ];
    }
}
