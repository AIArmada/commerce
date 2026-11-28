<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Wallet\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherWalletReader;
use AIArmada\Vouchers\Features\Lifecycle\Api\WalletVoucherState;
use AIArmada\Vouchers\Features\Wallet\Api\WalletTimestampTransition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $voucher_id
 * @property string|null $holder_type
 * @property string|null $holder_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $redeemed_at
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Model|null $holder
 * @property-read Model|null $owner
 */
final class VoucherWallet extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'vouchers.owner';

    protected $fillable = [
        'voucher_id',
        'holder_type',
        'holder_id',
        'owner_type',
        'owner_id',
        'claimed_at',
        'redeemed_at',
        'metadata',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';
    }

    public function voucherSnapshot(): ?VoucherData
    {
        return $this->voucherState()?->voucher;
    }

    private function voucherState(): ?WalletVoucherState
    {
        return app(VoucherWalletReader::class)->readMany([$this->voucher_id])[$this->voucher_id] ?? null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function claim(): void
    {
        if (! $this->exists || $this->getKey() === null || $this->claimed_at !== null) {
            return;
        }

        app(WalletTimestampTransition::class)->handle((string) $this->getKey(), false);
        $this->refresh();
    }

    public function markAsRedeemed(): void
    {
        if (! $this->exists || $this->getKey() === null || $this->redeemed_at !== null) {
            return;
        }

        app(WalletTimestampTransition::class)->handle((string) $this->getKey(), true);
        $this->refresh();
    }

    public function isAvailable(): bool
    {
        return $this->claimed_at !== null && $this->redeemed_at === null;
    }

    public function isExpired(): bool
    {
        return $this->voucherState()?->expired ?? false;
    }

    public function canBeUsed(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->voucherState()?->redeemable ?? false;
    }

    protected function casts(): array
    {
        return [
            'claimed_at' => 'immutable_datetime',
            'redeemed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return [
            'voucher_id',
            'holder_type',
            'holder_id',
            'owner_type',
            'owner_id',
            'claimed_at',
            'redeemed_at',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'voucher_id',
                'holder_type',
                'holder_id',
                'claimed_at',
                'redeemed_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vouchers');
    }
}
