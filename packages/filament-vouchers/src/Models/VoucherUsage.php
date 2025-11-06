<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Models;

use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
use AIArmada\Vouchers\Models\VoucherUsage as BaseVoucherUsage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string|null $cart_url
 * @property string $currency
 * @property int $discount_amount
 * @property string $channel
 * @property string $redeemed_by_type
 * @property string $cart_identifier
 * @property string $user_identifier
 * @property string|null $notes
 * @property int $redeemed_by_id
 * @property array<string, mixed> $cart_snapshot
 * @property \Illuminate\Support\Carbon $used_at
 */
final class VoucherUsage extends BaseVoucherUsage
{
    public function cartSnapshot(): BelongsTo
    {
        $cartModel = app(FilamentCartBridge::class)->getCartModel();

        if (! $cartModel) {
            throw new LogicException('Cart integration is unavailable. Install aiarmada/filament-cart to enable cart relations.');
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $cartModel */
        return $this->belongsTo($cartModel, 'cart_identifier', 'identifier');
    }

    protected function cartUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $identifier = $this->getAttribute('cart_identifier');

                return app(FilamentCartBridge::class)->resolveCartUrl(is_string($identifier) ? $identifier : null);
            }
        );
    }
}
