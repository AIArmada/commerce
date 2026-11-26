<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\States\VoucherStatus;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Filament\authorize;

final class VouchersRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'vouchers';

    protected static ?string $title = 'Vouchers';

    /** @return HasMany<Voucher, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        $owner = $this->getOwnerRecord();
        $relation = $owner->hasMany(Voucher::class, 'affiliate_id');

        if (config('vouchers.owner.enabled', false)) {
            $relation->where('owner_type', $owner->getAttribute('owner_type'))
                ->where('owner_id', $owner->getAttribute('owner_id'));
        }

        return $relation;
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Affiliate || ! class_exists(Voucher::class) || ! class_exists(VoucherResource::class)) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        try {
            return authorize('viewAny', Voucher::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (VoucherType | string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static function (VoucherStatus | string $state): string {
                        $status = $state instanceof VoucherStatus ? $state : VoucherStatus::fromString($state);

                        return match (true) {
                            $status instanceof Active => 'success',
                            $status instanceof Paused => 'warning',
                            $status instanceof Expired => 'danger',
                            $status instanceof Depleted => 'gray',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(fn (VoucherStatus | string $state): string => $state instanceof VoucherStatus ? $state->label() : VoucherStatus::fromString($state)->label())
                    ->sortable(),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (Voucher $record): string => VoucherResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (Voucher $record): bool => VoucherResource::canEdit($record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No vouchers linked to this affiliate');
    }
}
