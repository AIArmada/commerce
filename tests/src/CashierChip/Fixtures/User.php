<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Fixtures;

use AIArmada\CashierChip\Billable;
use AIArmada\CashierChip\Cashier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected ?string $pendingChipCustomerId = null;

    /**
     * @var array<string, mixed>
     */
    protected array $pendingLegacyPaymentMethodState = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    public function setChipIdAttribute(?string $value): void
    {
        $this->pendingChipCustomerId = is_string($value) && $value !== '' ? $value : null;
    }

    public function setDefaultPmIdAttribute(?string $value): void
    {
        $this->pendingLegacyPaymentMethodState['default_pm_id'] = $value;
    }

    public function setPmTypeAttribute(?string $value): void
    {
        $this->pendingLegacyPaymentMethodState['pm_type'] = $value;
    }

    public function setPmLastFourAttribute(?string $value): void
    {
        $this->pendingLegacyPaymentMethodState['pm_last_four'] = $value;
    }

    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->pendingChipCustomerId !== null) {
                Cashier::chipCustomerDirectory()->link($user, $user->pendingChipCustomerId);
                $user->pendingChipCustomerId = null;
            }

            if ($user->pendingLegacyPaymentMethodState === []) {
                return;
            }

            $state = $user->pendingLegacyPaymentMethodState;
            $user->pendingLegacyPaymentMethodState = [];

            $defaultPaymentMethodId = $state['default_pm_id'] ?? null;
            $paymentMethodType = $state['pm_type'] ?? null;
            $paymentMethodLastFour = $state['pm_last_four'] ?? null;

            if ($defaultPaymentMethodId === null && $paymentMethodType === null && $paymentMethodLastFour === null) {
                Cashier::paymentMethodStore()->deleteAllForBillable($user);

                return;
            }

            $resolvedPaymentMethodId = is_string($defaultPaymentMethodId) && $defaultPaymentMethodId !== ''
                ? $defaultPaymentMethodId
                : 'tok_fixture_' . $user->getKey();

            $resolvedType = is_string($paymentMethodType) && $paymentMethodType !== '' ? $paymentMethodType : null;
            $resolvedLastFour = is_string($paymentMethodLastFour) && $paymentMethodLastFour !== '' ? $paymentMethodLastFour : null;

            Cashier::paymentMethodStore()->saveForBillable(
                $user,
                $resolvedPaymentMethodId,
                [
                    'type' => $resolvedType,
                    'brand' => $resolvedType,
                    'last_four' => $resolvedLastFour,
                    'metadata' => [
                        'source' => 'cashier-chip-test-fixture',
                    ],
                ],
                true,
            );
        });
    }
}
