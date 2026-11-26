<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Relations;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Conversions\States\PaidConversion;
use AIArmada\Affiliates\Features\Conversions\States\RejectedConversion;
use AIArmada\Affiliates\Support\Integrations\CartBridge;
use AIArmada\Affiliates\Support\Integrations\VoucherBridge;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Filament\authorize;

final class ConversionsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'conversions';

    /** @return HasMany<AffiliateConversion, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(AffiliateConversion::class, 'affiliate_id');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Affiliate) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        try {
            return authorize('viewAny', AffiliateConversion::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_reference')
            ->columns([
                TextColumn::make('external_reference')
                    ->label('Reference')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('origin')
                    ->label('Origin')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('source_ref')
                    ->label('Source Ref')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->commission_currency))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ConversionStatus | string $state): string => AffiliateConversionsTable::statusColor($state))
                    ->formatStateUsing(fn (ConversionStatus | string $state): string => AffiliateConversionsTable::statusLabel($state))
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliateConversion $record): string => AffiliateConversionResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
                Action::make('cart')
                    ->label('Cart')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->visible(fn () => app(CartBridge::class)->isAvailable())
                    ->url(fn (AffiliateConversion $record): ?string => app(CartBridge::class)->resolveUrl(
                        $record->subject_key,
                        $record->subject_instance
                    ))
                    ->openUrlInNewTab(),
                Action::make('voucher')
                    ->label('Voucher')
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(fn (AffiliateConversion $record): bool => $record->voucher_code !== null && app(VoucherBridge::class)->isAvailable())
                    ->url(fn (AffiliateConversion $record): ?string => app(VoucherBridge::class)->resolveUrl($record->voucher_code))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(ApprovedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, ApprovedConversion::class)),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(RejectedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, RejectedConversion::class)),
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('primary')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(PaidConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, PaidConversion::class)),
            ])
            ->emptyStateHeading('No conversions yet');
    }
}
