<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables;

use AIArmada\FilamentVouchers\Exports\VoucherUsageExporter;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\AffiliateReportingContextResolver;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Models\VoucherUsage;
use Filament\Actions\ExportAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class VoucherUsagesTable
{
    public static function configure(Table $table): Table
    {
        $affiliateReporting = app(AffiliateReportingContextResolver::class);
        $filters = [];

        if ($affiliateReporting->supportsAffiliateReporting()) {
            $filters[] = SelectFilter::make('affiliate_code')
                ->label('Affiliate')
                ->options($affiliateReporting->affiliateOptions())
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => $affiliateReporting->applyUsageFilters($query, ['affiliate_code' => $data['value'] ?? null]));

            $filters[] = SelectFilter::make('affiliate_source')
                ->label('Source')
                ->options($affiliateReporting->sourceOptions())
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => $affiliateReporting->applyUsageFilters($query, ['source' => $data['value'] ?? null]));

            $filters[] = SelectFilter::make('affiliate_medium')
                ->label('Medium')
                ->options($affiliateReporting->mediumOptions())
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => $affiliateReporting->applyUsageFilters($query, ['medium' => $data['value'] ?? null]));

            $filters[] = SelectFilter::make('affiliate_campaign')
                ->label('Campaign')
                ->options($affiliateReporting->campaignOptions())
                ->searchable()
                ->query(fn (Builder $query, array $data): Builder => $affiliateReporting->applyUsageFilters($query, ['campaign' => $data['value'] ?? null]));
        }

        $filters[] = SelectFilter::make('channel')
            ->label('Channel')
            ->options([
                VoucherUsage::CHANNEL_AUTOMATIC => 'Automatic',
                VoucherUsage::CHANNEL_MANUAL => 'Manual',
                VoucherUsage::CHANNEL_API => 'API',
            ]);

        return $table
            ->defaultSort('used_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['voucher', 'redeemedBy']))
            ->columns([
                TextColumn::make('user_identifier')
                    ->label('User')
                    ->state(static fn (VoucherUsage $record): string => $record->user_identifier)
                    ->wrap()
                    ->placeholder('N/A'),

                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->searchable()
                    ->url(fn (VoucherUsage $record): ?string => $record->voucher ? VoucherResource::getUrl('view', ['record' => $record->voucher]) : null)
                    ->placeholder('N/A'),

                TextColumn::make('affiliate_reporting')
                    ->label('Affiliate')
                    ->state(static fn (VoucherUsage $record): ?string => $affiliateReporting->affiliateLabel(
                        $affiliateReporting->resolve($record)
                    ))
                    ->description(static fn (VoucherUsage $record): ?string => $affiliateReporting->sourceMediumLabel(
                        $affiliateReporting->resolve($record)
                    ))
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('affiliate_campaign')
                    ->label('Campaign')
                    ->state(static fn (VoucherUsage $record): ?string => $affiliateReporting->resolve($record)['campaign'])
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(static fn (string $state): string => match ($state) {
                        VoucherUsage::CHANNEL_MANUAL => 'warning',
                        VoucherUsage::CHANNEL_API => 'info',
                        default => 'success',
                    })
                    ->icon(static fn (string $state): Heroicon => match ($state) {
                        VoucherUsage::CHANNEL_MANUAL => Heroicon::OutlinedClipboardDocumentCheck,
                        VoucherUsage::CHANNEL_API => Heroicon::OutlinedCommandLine,
                        default => Heroicon::OutlinedBolt,
                    }),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->formatStateUsing(static function ($state, VoucherUsage $record): string {
                        return MoneyHelper::formatMoney((int) $state, (string) ($record->currency ?? config('filament-vouchers.default_currency', 'MYR')));
                    })
                    ->alignEnd(),

                TextColumn::make('resolved_order_number')
                    ->label('Order Number')
                    ->toggleable()
                    ->state(fn (VoucherUsage $record): ?string => $affiliateReporting->orderNumber($record))
                    ->url(function (VoucherUsage $record) use ($affiliateReporting): ?string {
                        $orderId = $affiliateReporting->orderId($record);

                        if ($orderId === null) {
                            return null;
                        }

                        /** @var class-string|null $orderResourceClass */
                        $orderResourceClass = config('filament-vouchers.order_resource');

                        if (! $orderResourceClass || ! class_exists($orderResourceClass)) {
                            return null;
                        }

                        return $orderResourceClass::getUrl('view', ['record' => $orderId]);
                    })
                    ->placeholder('N/A'),

                TextColumn::make('used_at')
                    ->label('Redeemed At')
                    ->dateTime()
                    ->sortable(),

                IconColumn::make('metadata')
                    ->label('Notes?')
                    ->boolean()
                    ->tooltip('Voucher usage contains metadata or notes')
                    ->state(static fn (VoucherUsage $record): bool => ! empty($record->metadata) || ! empty($record->notes))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($filters)
            ->headerActions([
                ExportAction::make()
                    ->exporter(VoucherUsageExporter::class)
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->label('Export'),
            ])
            ->recordUrl(null);
    }
}
