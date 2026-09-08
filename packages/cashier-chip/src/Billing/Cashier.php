<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Billing;

use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Contracts\PaymentMethodStoreInterface;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Main Cashier class for CHIP payment gateway.
 *
 * Can be referenced as either `Cashier` or `CashierChip` for compatibility.
 */
final class Cashier
{
    /**
     * The Cashier Chip library version.
     */
    public const VERSION = '1.0.0';

    /**
     * Indicates if Cashier should register routes.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Indicates if Cashier will mark incomplete subscriptions as inactive.
     */
    public static bool $deactivateIncomplete = true;

    /**
     * The default customer model class name.
     *
     * @var class-string<Model>
     */
    public static string $customerModel = Model::class;

    /**
     * The subscription model class name.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The subscription item model class name.
     */
    public static string $subscriptionItemModel = SubscriptionItem::class;

    /**
     * The fake CHIP service for testing.
     */
    protected static ?FakeChipCollectService $fakeChip = null;

    /**
     * Indicates if the fake service is enabled.
     */
    protected static bool $isFake = false;

    /**
     * Boot-time defaults restored for each Octane request.
     *
     * @var array{
     *   remembered: bool,
     *   registersRoutes: bool,
     *   deactivatePastDue: bool,
     *   deactivateIncomplete: bool,
     *   customerModel: class-string<Model>,
     *   subscriptionModel: class-string<Model>,
     *   subscriptionItemModel: class-string<Model>,
     *   fakeChip: FakeChipCollectService|null,
     *   isFake: bool
     * }
     */
    private static array $octaneDefaults = [
        'remembered' => false,
        'registersRoutes' => true,
        'deactivatePastDue' => true,
        'deactivateIncomplete' => true,
        'customerModel' => Model::class,
        'subscriptionModel' => Subscription::class,
        'subscriptionItemModel' => SubscriptionItem::class,
        'fakeChip' => null,
        'isFake' => false,
    ];

    /**
     * Get the customer instance by its CHIP ID.
     *
     * @return (Model&BillableContract)|null
     */
    public static function findBillable(?string $chipId): ?Model
    {
        if (! $chipId) {
            return null;
        }

        $owner = null;
        if ((bool) config('cashier-chip.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            if ($owner === null) {
                return null;
            }
        }

        return static::resolveBillable($chipId, $owner);
    }

    /**
     * @return (Model&BillableContract)|null
     */
    private static function resolveBillable(string $chipId, ?Model $owner = null): ?Model
    {
        $link = static::chipCustomerDirectory()->findByChipCustomerId($chipId, $owner);
        $subject = $link?->subject;

        /** @var (Model&BillableContract)|null $billable */
        $billable = $subject instanceof Model && static::isBillableModel($subject)
            ? $subject
            : null;

        return $billable;
    }

    /**
     * Resolve a billable from a CHIP client id in system contexts (webhooks/events).
     *
     * In owner mode this fails closed when owner context is missing and, when enabled,
     * validates the resolved billable against the active owner boundary.
     *
     * @return (Model&BillableContract)|null
     */
    public static function findBillableForWebhook(?string $chipId): ?Model
    {
        if (! $chipId) {
            return null;
        }

        if (! (bool) config('cashier-chip.features.owner.enabled', false)) {
            return static::findBillable($chipId);
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return null;
        }

        $shouldValidateBillableOwner = (bool) config('cashier-chip.features.owner.validate_billable_owner', true);

        if (! $shouldValidateBillableOwner) {
            return static::resolveBillable($chipId);
        }

        return static::resolveBillable($chipId, $owner);
    }

    private static function isBillableModel(Model $subject): bool
    {
        if ($subject instanceof BillableContract) {
            return true;
        }

        return in_array(Billable::class, class_uses_recursive($subject), true);
    }

    public static function findSubscriptionForWebhook(Model $billable, string $subscriptionType): ?Subscription
    {
        $query = Subscription::query()
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', (string) $billable->getKey())
            ->where('type', $subscriptionType);

        if ((bool) config('cashier-chip.features.owner.enabled', false) && OwnerContext::resolve() === null) {
            return null;
        }

        return $query->first();
    }

    /**
     * Get the CHIP Collect service client.
     */
    public static function chip(): ChipCollectService | FakeChipCollectService
    {
        if (static::$isFake && static::$fakeChip) {
            return static::$fakeChip;
        }

        return app(ChipCollectService::class);
    }

    public static function chipCustomerDirectory(): ChipCustomerDirectoryInterface
    {
        return app(ChipCustomerDirectoryInterface::class);
    }

    public static function paymentMethodStore(): PaymentMethodStoreInterface
    {
        return app(PaymentMethodStoreInterface::class);
    }

    /**
     * Enable fake CHIP client for testing.
     */
    public static function fake(?FakeChipClient $fakeClient = null): FakeChipCollectService
    {
        static::$isFake = true;
        static::$fakeChip = new FakeChipCollectService($fakeClient);

        return static::$fakeChip;
    }

    /**
     * Get the fake CHIP service.
     */
    public static function getFake(): ?FakeChipCollectService
    {
        return static::$fakeChip;
    }

    /**
     * Determine if the fake service is enabled.
     */
    public static function isFake(): bool
    {
        return static::$isFake;
    }

    /**
     * Disable fake CHIP client and restore real service.
     */
    public static function unfake(): void
    {
        static::$isFake = false;
        static::$fakeChip = null;
    }

    /**
     * Reset the fake service state.
     */
    public static function resetFake(): void
    {
        if (static::$fakeChip) {
            static::$fakeChip->reset();
        }
    }

    /**
     * Snapshot boot-time defaults so Octane can restore them on each request.
     */
    public static function rememberOctaneDefaults(): void
    {
        self::$octaneDefaults = [
            'remembered' => true,
            'registersRoutes' => static::$registersRoutes,
            'deactivatePastDue' => static::$deactivatePastDue,
            'deactivateIncomplete' => static::$deactivateIncomplete,
            'customerModel' => static::$customerModel,
            'subscriptionModel' => static::$subscriptionModel,
            'subscriptionItemModel' => static::$subscriptionItemModel,
            'fakeChip' => static::$fakeChip,
            'isFake' => static::$isFake,
        ];
    }

    /**
     * Restore boot-time defaults before handling the next Octane request.
     */
    public static function restoreOctaneDefaults(): void
    {
        if (self::$octaneDefaults['remembered'] !== true) {
            return;
        }

        static::$registersRoutes = self::$octaneDefaults['registersRoutes'];
        static::$deactivatePastDue = self::$octaneDefaults['deactivatePastDue'];
        static::$deactivateIncomplete = self::$octaneDefaults['deactivateIncomplete'];
        static::useCustomerModel(self::$octaneDefaults['customerModel']);
        static::useSubscriptionModel(self::$octaneDefaults['subscriptionModel']);
        static::useSubscriptionItemModel(self::$octaneDefaults['subscriptionItemModel']);
        static::$fakeChip = self::$octaneDefaults['fakeChip'];
        static::$isFake = self::$octaneDefaults['isFake'];
    }

    public static function maximumAmount(): int
    {
        return max(1, (int) config('cashier-chip.billing.max_amount_minor', 100_000_000));
    }

    public static function isAmountWithinBounds(int $amount): bool
    {
        return $amount > 0 && $amount <= static::maximumAmount();
    }

    public static function assertAmountWithinBounds(int $amount): void
    {
        if (static::isAmountWithinBounds($amount)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Charge amount must be between 1 and %d minor units.',
            static::maximumAmount(),
        ));
    }

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): static
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     */
    public static function keepPastDueSubscriptionsActive(): static
    {
        static::$deactivatePastDue = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain incomplete subscriptions as active.
     */
    public static function keepIncompleteSubscriptionsActive(): static
    {
        static::$deactivateIncomplete = false;

        return new static;
    }

    /**
     * Set the customer model class name.
     *
     * @param  class-string<Model>  $customerModel
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     *
     * @param  class-string<Model>  $subscriptionModel
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the subscription item model class name.
     *
     * @param  class-string<Model>  $subscriptionItemModel
     */
    public static function useSubscriptionItemModel(string $subscriptionItemModel): void
    {
        static::$subscriptionItemModel = $subscriptionItemModel;
    }
}
