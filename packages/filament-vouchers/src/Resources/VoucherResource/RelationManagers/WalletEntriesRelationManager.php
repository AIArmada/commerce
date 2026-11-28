<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\WalletEntriesTable;
use AIArmada\Vouchers\Features\Wallet\Models\VoucherWallet;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Components\Attributes\Unexposed;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use function Filament\authorize;

final class WalletEntriesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'walletEntries';

    protected static ?string $title = 'Wallet Entries';

    protected static ?string $modelLabel = 'wallet entry';

    protected static ?string $pluralModelLabel = 'wallet entries';

    /** @return HasMany<VoucherWallet, Model> */
    #[Unexposed]
    public function getRelationship(): HasMany
    {
        return $this->getOwnerRecord()->hasMany(VoucherWallet::class, 'voucher_id');
    }

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof Voucher) {
            return false;
        }

        if (static::shouldSkipAuthorization()) {
            return true;
        }

        try {
            return authorize('viewAny', VoucherWallet::class, static::shouldCheckPolicyExistence())->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }

    public function table(Table $table): Table
    {
        return WalletEntriesTable::configure($table);
    }
}
