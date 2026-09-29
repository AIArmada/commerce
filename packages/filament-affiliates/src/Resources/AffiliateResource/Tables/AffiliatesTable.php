<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables;

use AIArmada\Affiliates\Actions\Affiliates\ApproveAffiliate;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Pending;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->icon(Heroicon::OutlinedLink)
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (Affiliate $record): ?string => $record->default_voucher_code ? "Voucher: {$record->default_voucher_code}" : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (AffiliateStatus | string $state): string => AffiliateStatus::fromString($state)->color())
                    ->formatStateUsing(fn (AffiliateStatus | string $state): string => AffiliateStatus::fromString($state)->label())
                    ->sortable(),

                TextColumn::make('registration_approval_mode')
                    ->label('Admitted under')
                    ->formatStateUsing(fn (string $state): string => RegistrationApprovalMode::tryFrom($state)?->label() ?? $state)
                    ->toggleable(),

                TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->state(function (Affiliate $record): string {
                        $type = $record->commission_type instanceof CommissionType
                            ? $record->commission_type
                            : CommissionType::from((string) $record->commission_type);

                        $value = (int) $record->commission_rate;

                        return $type === CommissionType::Percentage
                            ? number_format($value / 100, 2) . ' %'
                            : MoneyFormatter::formatMinor($value, $record->currency);
                    })
                    ->badge()
                    ->color('primary'),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AffiliateStatus::options()),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->authorize(fn (): bool => FilamentPermission::hasAnyAbility(['affiliate.update']))
                    ->visible(fn (Affiliate $record): bool => $record->status instanceof Pending)
                    ->requiresConfirmation()
                    ->action(fn (Affiliate $record): bool => self::approveAffiliate($record)),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function approveAffiliate(Affiliate $record): bool
    {
        Gate::authorize('update', $record);

        $key = $record->getKey();

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $key);
        }

        return DB::transaction(function () use ($key): bool {
            $affiliate = Affiliate::query()->whereKey($key)->lockForUpdate()->firstOrFail();

            if (! $affiliate->status instanceof Pending) {
                return false;
            }

            app(ApproveAffiliate::class)->handle($affiliate);

            return true;
        }, attempts: 3);
    }
}
