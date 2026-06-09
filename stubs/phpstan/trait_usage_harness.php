<?php

declare(strict_types=1);

namespace AIArmada\Commerce\PHPStan\TraitUsageHarness;

use AIArmada\Cart\Concerns\Buyable;
use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\CommerceSupport\Traits\FormatsMoney;
use AIArmada\CommerceSupport\Traits\HasPaymentStatus;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use AIArmada\Customers\Concerns\HasCustomerProfile;
use AIArmada\FilamentAuthz\Concerns\CanBeImpersonated;
use AIArmada\FilamentAuthz\Concerns\HasAuthzScope;
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\CommerceSupport\Models\AuthzScope;
use AIArmada\Vouchers\Traits\HasVoucherOwnership;
use AIArmada\Vouchers\Traits\HasVouchers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class PageHarnessBase
{
    public static function canAccess(): bool
    {
        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}

abstract class WidgetHarnessBase
{
    public static function canView(): bool
    {
        return true;
    }
}

abstract class BuyableHarness extends Model implements BuyableInterface
{
    use Buyable;
}

abstract class PaymentStatusHarness extends Model
{
    use HasPaymentStatus;
}

abstract class FormatsMoneyHarness extends Model
{
    use FormatsMoney;
}

abstract class OwnerContextJobHarness
{
    use OwnerContextJob;

    protected function performJob(): void {}
}

/**
 * @property string $email
 * @property string|null $name
 * @property string|null $phone
 */
abstract class CustomerProfileHarness extends Authenticatable
{
    use HasCustomerProfile;
}

abstract class PanelAuthzHarness extends Authenticatable
{
    use CanBeImpersonated;
    use HasPanelAuthz;
}

/**
 * @property-read AuthzScope|null $authzScope
 */
abstract class AuthzScopeHarness extends Model
{
    use HasAuthzScope;
}

abstract class PageAuthzHarness extends PageHarnessBase
{
    use HasPageAuthz;
}

abstract class WidgetAuthzHarness extends WidgetHarnessBase
{
    use HasWidgetAuthz;
}

abstract class VoucherOwnershipHarness extends Model
{
    use HasVoucherOwnership;
}

abstract class VoucherHolderHarness extends Model
{
    use HasVouchers;
}
