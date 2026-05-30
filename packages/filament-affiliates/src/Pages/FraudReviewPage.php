<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Actions\BulkFraudReviewAction;
use AIArmada\FilamentAffiliates\Actions\UpdateAffiliateFraudSignalStatus;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
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
use UnitEnum;

final class FraudReviewPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Fraud Review';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation_group');
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
                OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                    ->where('status', FraudSignalStatus::Detected)
                    ->with(['affiliate', 'conversion'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rule_code')
                    ->label('Signal')
                    ->badge()
                    ->color(fn ($state): string => match ((string) $state) {
                        'velocity' => 'danger',
                        'velocity_abuse' => 'danger',
                        'ip_duplicate' => 'warning',
                        'self_referral' => 'danger',
                        'pattern' => 'warning',
                        'suspicious_pattern' => 'warning',
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
                    ->label('Risk Score')
                    ->formatStateUsing(fn ($state): string => $state . '%'),

                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_code')
                    ->options([
                        'velocity' => 'Velocity Abuse',
                        'ip_duplicate' => 'IP Duplicate',
                        'self_referral' => 'Self Referral',
                        'pattern' => 'Suspicious Pattern',
                        'cookie_stuffing' => 'Cookie Stuffing',
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
        $base = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
            ->where('status', FraudSignalStatus::Detected);

        return [
            'pendingCount' => (clone $base)->count(),
            'criticalCount' => (clone $base)
                ->where('severity', 'critical')
                ->count(),
        ];
    }
}
