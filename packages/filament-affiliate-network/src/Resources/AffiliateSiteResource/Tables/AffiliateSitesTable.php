<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Tables;

use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\AdjustMerchantPrepaidBalance;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\ReinstateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RejectSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\SuspendSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Actions\SyncSiteCatalog;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

final class AffiliateSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SiteStatus | string $state): string => $state instanceof SiteStatus ? $state->label() : (SiteStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->color(fn (SiteStatus | string $state): string => $state instanceof SiteStatus ? $state->color() : (SiteStatus::tryFrom((string) $state)?->color() ?? 'gray')),

                TextColumn::make('offers_count')
                    ->label('Offers')
                    ->counts('offers')
                    ->sortable(),

                TextColumn::make('default_locking_days')
                    ->label('Locking (days)')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('prepaid_balance_minor')
                    ->label('Prepaid balance')
                    ->formatStateUsing(fn (AffiliateSite $record): ?string => $record->prepaid_balance_minor === null
                        ? null
                        : MoneyFormatter::formatMinor((int) $record->prepaid_balance_minor, self::currency($record)))
                    ->placeholder('Postpaid')
                    ->toggleable(),

                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Actions\EditAction::make(),
                SyncSiteCatalog::make(),
                Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(VerifySite::class)->execute($record));
                    }),
                Actions\Action::make('verify_strategy')
                    ->label('Verify by Strategy')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->form([
                        Select::make('method')
                            ->label('Verification strategy')
                            ->options([
                                'dns' => 'DNS',
                                'meta_tag' => 'Meta tag',
                                'file' => 'File',
                            ])
                            ->required(),
                    ])
                    ->action(function (AffiliateSite $record, array $data): void {
                        try {
                            self::withSiteOwner($record, fn (): AffiliateSite => app(VerifySiteByStrategy::class)->execute($record, (string) $data['method']));

                            Notification::make()
                                ->title('Site verified')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Verification failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('adjust_prepaid')
                    ->label('Adjust Prepaid Balance')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->form([
                        TextInput::make('delta_minor')
                            ->label('Balance delta (minor)')
                            ->rule('integer')
                            ->required()
                            ->helperText('Positive to top up, negative to debit.'),
                    ])
                    ->action(function (AffiliateSite $record, array $data): void {
                        $balance = app(AdjustMerchantPrepaidBalance::class)->execute((string) $record->getKey(), (int) $data['delta_minor']);

                        Notification::make()
                            ->title('Prepaid balance adjusted')
                            ->body(MoneyFormatter::formatMinor($balance, self::currency($record)))
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('rotate_catalog_token')
                    ->label('Rotate Catalog Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isVerified())
                    ->action(function (AffiliateSite $record): void {
                        $token = self::withSiteOwner($record, fn (): string => app(RotateCatalogToken::class)->execute($record));

                        Notification::make()
                            ->title('New catalog token')
                            ->body($token)
                            ->persistent()
                            ->send();
                    }),
                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(RejectSite::class)->execute($record));
                    }),
                Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isVerified())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(SuspendSite::class)->execute($record));
                    }),
                Actions\Action::make('reinstate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isSuspended())
                    ->action(function (AffiliateSite $record): void {
                        self::withSiteOwner($record, fn (): AffiliateSite => app(ReinstateSite::class)->execute($record));
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (SiteStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    private static function currency(AffiliateSite $record): string
    {
        $currency = $record->budget_currency;

        if (is_string($currency) && mb_trim($currency) !== '') {
            return mb_strtoupper($currency);
        }

        return mb_strtoupper((string) config('affiliate-network.currency.default', 'MYR'));
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private static function withSiteOwner(AffiliateSite $record, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId($record->owner_type, $record->owner_id);

        return OwnerContext::withOwner($owner, $callback);
    }
}
