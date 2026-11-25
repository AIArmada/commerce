<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Widgets;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Actions\UpdateAffiliateFraudSignalStatus;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Resources\AffiliateFraudSignalResource;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class FraudAlertWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected static ?string $heading = 'Fraud Alerts';

    public static function canView(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliates.fraud.view-any', 'affiliate.viewAny', 'affiliate.approve']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateFraudSignal::query()
                    ->with('affiliate')
                    ->where('status', FraudSignalStatus::Detected)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Detected')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rule_code')
                    ->label('Rule')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (FraudSeverity $state) => match ($state) {
                        FraudSeverity::Low => 'gray',
                        FraudSeverity::Medium => 'warning',
                        FraudSeverity::High => 'danger',
                        FraudSeverity::Critical => 'danger',
                    }),

                Tables\Columns\TextColumn::make('risk_points')
                    ->label('Risk points'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->actions([
                Action::make('review')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AffiliateFraudSignal $record): string => AffiliateFraudSignalResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.approve', 'affiliates.fraud.update']))
                    ->action(fn (AffiliateFraudSignal $record): AffiliateFraudSignal => UpdateAffiliateFraudSignalStatus::run(
                        $record,
                        FraudSignalStatus::Dismissed,
                    )),
            ])
            ->paginated(false)
            ->emptyStateHeading('No fraud alerts')
            ->emptyStateDescription('No suspicious activity detected.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
