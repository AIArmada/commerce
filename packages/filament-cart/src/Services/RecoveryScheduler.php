<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Models\RecoveryAttempt;
use AIArmada\Cart\Models\RecoveryCampaign;
use AIArmada\Cart\States\Cancelled;
use AIArmada\Cart\States\Queued;
use AIArmada\Cart\States\RecoveryAttemptStatus;
use AIArmada\Cart\States\Scheduled;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Settings\CartRecoverySettings;
use AIArmada\Orders\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service for scheduling cart recovery attempts.
 */
class RecoveryScheduler
{
    /**
     * Schedule recovery attempts for eligible carts based on campaign criteria.
     */
    public function scheduleForCampaign(RecoveryCampaign $campaign): int
    {
        $settings = $this->resolveSettings();

        if ($settings !== null && ! $settings->recoveryEnabled) {
            return 0;
        }

        if (! $campaign->isActive()) {
            return 0;
        }

        $eligibleCarts = $this->findEligibleCarts($campaign);
        $scheduled = 0;

        foreach ($eligibleCarts as $cart) {
            if ($this->scheduleAttemptForCart($campaign, $cart)) {
                $scheduled++;
            }
        }

        $campaign->update([
            'total_targeted' => $campaign->total_targeted + $scheduled,
            'last_run_at' => now(),
        ]);

        return $scheduled;
    }

    /**
     * Process all scheduled attempts that are due.
     *
     * @return array{processed: int, failed: int}
     */
    public function processScheduledAttempts(): array
    {
        $settings = $this->resolveSettings();

        if ($settings !== null && ! $settings->recoveryEnabled) {
            return [
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $dueAttempts = RecoveryAttempt::query()->forOwner()
            ->where('status', RecoveryAttemptStatus::normalize(Scheduled::class))
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(100)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($dueAttempts as $attempt) {
            try {
                $this->queueAttempt($attempt);
                $processed++;
            } catch (Throwable $e) {
                $attempt->markAsFailed($e->getMessage());
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * Schedule the next attempt for a cart if applicable.
     */
    public function scheduleNextAttempt(RecoveryAttempt $previousAttempt): ?RecoveryAttempt
    {
        $settings = $this->resolveSettings();
        $campaign = $previousAttempt->campaign;
        $cart = $previousAttempt->cart;

        // Check if cart was recovered
        // @phpstan-ignore property.notFound
        if ($cart && $cart->recovered_at !== null) {
            return null;
        }

        // Check if max attempts reached
        $attemptCount = RecoveryAttempt::query()->forOwner()
            ->where('campaign_id', $campaign->id)
            ->where('cart_id', $previousAttempt->cart_id)
            ->count();

        if ($attemptCount >= $this->getMaxAttempts($campaign, $settings)) {
            return null;
        }

        /** @var Cart $cart */
        return $this->createAttempt(
            $campaign,
            $cart,
            $this->adjustScheduledFor($cart, now()->addHours($campaign->attempt_interval_hours), $settings),
            $attemptCount + 1,
            $settings,
        );
    }

    /**
     * Cancel all scheduled attempts for a cart.
     */
    public function cancelAttemptsForCart(string $cartId): int
    {
        return RecoveryAttempt::query()->forOwner()
            ->where('cart_id', $cartId)
            ->where('status', RecoveryAttemptStatus::normalize(Scheduled::class))
            ->update(['status' => Cancelled::class]);
    }

    /**
     * Find carts eligible for a campaign.
     *
     * @return Collection<int, Cart>
     */
    private function findEligibleCarts(RecoveryCampaign $campaign): Collection
    {
        $settings = $this->resolveSettings();
        $cartTable = (new Cart)->getTable();

        $query = Cart::query()->forOwner()
            ->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('items_count', '>', 0);

        if (RecoveryCampaign::ownerScopingEnabled() && $campaign->owner_type !== null && $campaign->owner_id !== null) {
            $query->where('owner_type', $campaign->owner_type)->where('owner_id', $campaign->owner_id);
        }

        // Apply trigger delay
        $abandonedBefore = now()->subMinutes($campaign->trigger_delay_minutes);
        $query->where('checkout_abandoned_at', '<=', $abandonedBefore);

        // Apply cart value filters
        if ($campaign->min_cart_value_cents !== null) {
            $query->where('subtotal', '>=', $campaign->min_cart_value_cents);
        }

        if ($settings !== null && $settings->minCartValue > 0) {
            $query->where('subtotal', '>=', $settings->minCartValue);
        }

        if ($campaign->max_cart_value_cents !== null) {
            $query->where('subtotal', '<=', $campaign->max_cart_value_cents);
        }

        // Apply item count filters
        if ($campaign->min_items !== null) {
            $query->where('items_count', '>=', $campaign->min_items);
        }

        if ($campaign->max_items !== null) {
            $query->where('items_count', '<=', $campaign->max_items);
        }

        // Exclude carts already in this campaign
        $query->whereNotExists(function ($subquery) use ($campaign, $cartTable): void {
            $prefix = config('filament-cart.database.table_prefix', 'cart_');
            $subquery->select(DB::raw(1))
                ->from($prefix . 'recovery_attempts')
                ->whereColumn($prefix . 'recovery_attempts.cart_id', $cartTable . '.id')
                ->where($prefix . 'recovery_attempts.campaign_id', $campaign->id);
        });

        // Only get carts with contact info
        $query->where(function ($q): void {
            $q->whereNotNull('metadata->email')
                ->orWhereNotNull('metadata->phone');
        });

        if ($settings !== null && $settings->excludeRepeatRecoveries) {
            $query->whereNotExists(function ($subquery) use ($cartTable): void {
                $prefix = config('filament-cart.database.table_prefix', 'cart_');
                $subquery->select(DB::raw(1))
                    ->from($cartTable . ' as recovered_cart')
                    ->whereNotNull('recovered_cart.recovered_at')
                    ->where('recovered_cart.recovered_at', '>=', now()->subDays(30))
                    ->where(function ($match) use ($cartTable): void {
                        $match->whereColumn("{$cartTable}.metadata->email", 'recovered_cart.metadata->email')
                            ->orWhereColumn("{$cartTable}.metadata->phone", 'recovered_cart.metadata->phone');
                    });
            });
        }

        return $query->limit(500)->get();
    }

    /**
     * Schedule an attempt for a specific cart.
     */
    private function scheduleAttemptForCart(RecoveryCampaign $campaign, Cart $cart): bool
    {
        $settings = $this->resolveSettings();

        if (! $this->passesExclusions($cart, $settings)) {
            return false;
        }

        if ($this->isWeeklyLimitReached($cart, $settings)) {
            return false;
        }

        $scheduledFor = $this->adjustScheduledFor($cart, now()->addMinutes(rand(1, 15)), $settings);

        $attempt = $this->createAttempt($campaign, $cart, $scheduledFor, 1, $settings);

        return $attempt !== null;
    }

    /**
     * Create a recovery attempt.
     */
    private function createAttempt(
        RecoveryCampaign $campaign,
        Cart $cart,
        CarbonInterface $scheduledFor,
        int $attemptNumber,
        ?CartRecoverySettings $settings = null,
    ): ?RecoveryAttempt {
        $email = $cart->email ?? ($cart->metadata['email'] ?? null);
        $phone = $cart->phone ?? ($cart->metadata['phone'] ?? null);
        $name = $cart->customer_name ?? ($cart->metadata['customer_name'] ?? null);

        if ($email === null && $phone === null) {
            return null;
        }

        // Determine template (A/B testing)
        $templateId = $campaign->control_template_id;
        $isControl = true;
        $isVariant = false;

        if ($campaign->ab_testing_enabled && $campaign->variant_template_id) {
            $isVariant = rand(1, 100) <= $campaign->ab_test_split_percent;
            if ($isVariant) {
                $templateId = $campaign->variant_template_id;
                $isControl = false;
            }
        }

        // Generate discount code if applicable
        $discountCode = null;
        $discountValueCents = null;

        if ($campaign->offer_discount && $campaign->discount_value) {
            $discountCode = $this->generateDiscountCode($campaign, $cart);
            $discountValueCents = $campaign->discount_type === 'percentage'
                ? (int) ($cart->subtotal * $campaign->discount_value / 100)
                : $campaign->discount_value;
        }

        if ($settings !== null && $attemptNumber > $this->getMaxAttempts($campaign, $settings)) {
            return null;
        }

        return RecoveryAttempt::create([
            'campaign_id' => $campaign->id,
            'cart_id' => $cart->id,
            'template_id' => $templateId,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'recipient_name' => $name,
            'channel' => $campaign->strategy === 'multi_channel' ? 'email' : $campaign->strategy,
            'status' => Scheduled::class,
            'attempt_number' => $attemptNumber,
            'is_control' => $isControl,
            'is_variant' => $isVariant,
            'discount_code' => $discountCode,
            'discount_value_cents' => $discountValueCents,
            'free_shipping_offered' => $campaign->offer_free_shipping,
            'offer_expires_at' => $campaign->urgency_hours ? now()->addHours($campaign->urgency_hours) : null,
            'cart_value_cents' => $cart->subtotal ?? 0,
            'cart_items_count' => $cart->items_count ?? 0,
            'scheduled_for' => $scheduledFor,
        ]);
    }

    private function resolveSettings(): ?CartRecoverySettings
    {
        try {
            /** @var CartRecoverySettings $settings */
            $settings = app(CartRecoverySettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }

    private function getMaxAttempts(RecoveryCampaign $campaign, ?CartRecoverySettings $settings): int
    {
        if ($settings === null) {
            return $campaign->max_attempts;
        }

        return min($campaign->max_attempts, $settings->maxRecoveryAttempts);
    }

    private function adjustScheduledFor(Cart $cart, CarbonInterface $scheduledFor, ?CartRecoverySettings $settings): CarbonInterface
    {
        if ($settings === null) {
            return $scheduledFor;
        }

        $timezone = (string) config('app.timezone', 'UTC');

        if ($settings->respectUserTimezone && is_string($cart->metadata['timezone'] ?? null)) {
            $timezone = (string) $cart->metadata['timezone'];
        }

        $blockedDays = collect($settings->blockedDays)
            ->pluck('day')
            ->filter()
            ->map(fn (string $day): string => mb_strtolower($day))
            ->all();

        $candidate = Carbon::parse($scheduledFor->toDateTimeString(), $timezone);

        for ($i = 0; $i < 7; $i++) {
            $dayName = mb_strtolower($candidate->englishDayOfWeek);

            if (! in_array($dayName, $blockedDays, true)) {
                if ($candidate->hour < $settings->sendStartHour) {
                    $candidate->setTime($settings->sendStartHour, 0);
                }

                if ($candidate->hour >= $settings->sendEndHour) {
                    $candidate = $candidate->addDay()->setTime($settings->sendStartHour, 0);

                    continue;
                }

                return $candidate->setTimezone(config('app.timezone', 'UTC'));
            }

            $candidate = $candidate->addDay()->setTime($settings->sendStartHour, 0);
        }

        return $scheduledFor;
    }

    private function isWeeklyLimitReached(Cart $cart, ?CartRecoverySettings $settings): bool
    {
        if ($settings === null || $settings->maxMessagesPerCustomerPerWeek <= 0) {
            return false;
        }

        $email = $cart->email ?? ($cart->metadata['email'] ?? null);
        $phone = $cart->phone ?? ($cart->metadata['phone'] ?? null);

        if ($email === null && $phone === null) {
            return false;
        }

        $query = RecoveryAttempt::query()->forOwner()
            ->where('created_at', '>=', now()->subDays(7));

        if ($email !== null) {
            $query->where('recipient_email', $email);
        } elseif ($phone !== null) {
            $query->where('recipient_phone', $phone);
        }

        return $query->count() >= $settings->maxMessagesPerCustomerPerWeek;
    }

    private function passesExclusions(Cart $cart, ?CartRecoverySettings $settings): bool
    {
        if ($settings === null) {
            return true;
        }

        if ($settings->excludeRepeatRecoveries) {
            $email = $cart->email ?? ($cart->metadata['email'] ?? null);
            $phone = $cart->phone ?? ($cart->metadata['phone'] ?? null);

            if ($email !== null || $phone !== null) {
                $query = Cart::query()->forOwner()
                    ->whereNotNull('recovered_at')
                    ->where('recovered_at', '>=', now()->subDays(30))
                    ->where(function ($q) use ($email, $phone): void {
                        if ($email !== null) {
                            $q->where('metadata->email', $email);
                        }

                        if ($phone !== null) {
                            $q->orWhere('metadata->phone', $phone);
                        }
                    });

                if ($query->exists()) {
                    return false;
                }
            }
        }

        if ($settings->excludeIfOrderedWithinDays > 0 && class_exists(Order::class)) {
            $customerId = $cart->metadata['customer_id'] ?? null;
            $customerType = $cart->metadata['customer_type'] ?? null;

            if (is_string($customerId) && is_string($customerType)) {
                $orders = Order::query();

                if (method_exists(Order::class, 'scopeForOwner')) {
                    $orders->forOwner();
                }

                $recentOrderExists = $orders
                    ->where('customer_id', $customerId)
                    ->where('customer_type', $customerType)
                    ->where('created_at', '>=', now()->subDays($settings->excludeIfOrderedWithinDays))
                    ->exists();

                if ($recentOrderExists) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Queue an attempt for sending.
     */
    private function queueAttempt(RecoveryAttempt $attempt): void
    {
        $attempt->update([
            'status' => Queued::class,
            'queued_at' => now(),
        ]);

        // Dispatch to queue (handled by RecoveryDispatcher)
        dispatch(fn () => app(RecoveryDispatcher::class)->dispatch($attempt));
    }

    /**
     * Generate a unique discount code for this recovery.
     */
    private function generateDiscountCode(RecoveryCampaign $campaign, Cart $cart): string
    {
        return mb_strtoupper(sprintf(
            'RECOVER-%s-%s',
            mb_substr($campaign->id, 0, 4),
            Str::random(6),
        ));
    }
}
