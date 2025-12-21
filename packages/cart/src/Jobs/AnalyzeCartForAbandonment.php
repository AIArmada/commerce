<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use AIArmada\Cart\AI\AbandonmentPrediction;
use AIArmada\Cart\AI\AbandonmentPredictor;
use AIArmada\Cart\AI\RecoveryOptimizer;
use AIArmada\Cart\AI\RecoveryStrategy;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Support\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to analyze carts for abandonment and trigger recovery strategies.
 *
 * This job runs periodically to:
 * 1. Identify carts at risk of abandonment
 * 2. Predict abandonment probability
 * 3. Queue appropriate recovery interventions
 */
final class AnalyzeCartForAbandonment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public readonly ?string $cartId = null,
        public readonly int $batchSize = 100,
        public readonly ?string $ownerType = null,
        public readonly string | int | null $ownerId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->cartId !== null) {
            $this->analyzeSpecificCart($this->cartId);

            return;
        }

        $this->analyzeAbandonedCarts();
    }

    /**
     * Get the tags for the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return $this->cartId
            ? ['cart-abandonment', "cart:{$this->cartId}"]
            : ['cart-abandonment', 'batch'];
    }

    /**
     * Analyze a specific cart.
     */
    private function analyzeSpecificCart(
        string $cartId,
    ): void {
        $cartsTable = config('cart.database.table', 'carts');
        $cartRecord = DB::table($cartsTable)->where('id', $cartId);

        if ((bool) config('cart.owner.enabled', false)) {
            CartOwnerScope::applyForOwner($cartRecord, $this->ownerType, $this->ownerId);
        }

        $cartRecord = $cartRecord->first();

        if (! $cartRecord) {
            Log::warning('Cart not found for abandonment analysis', ['cart_id' => $cartId]);

            return;
        }

        $owner = OwnerContext::fromTypeAndId($cartRecord->owner_type ?? null, $cartRecord->owner_id ?? null);

        OwnerContext::withOwner($owner, function () use ($cartRecord, $cartId): void {
            $predictor = app(AbandonmentPredictor::class);
            $optimizer = app(RecoveryOptimizer::class);
            $cartManager = app(CartManager::class);

            try {
                $cart = $cartManager
                    ->setIdentifier($cartRecord->identifier)
                    ->setInstance($cartRecord->instance ?? 'default')
                    ->getCurrentCart();

                $prediction = $predictor->predict($cart, (string) $cartRecord->identifier);

                if ($prediction->needsIntervention()) {
                    $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

                    $this->queueIntervention(
                        $cart->getId(),
                        $strategy,
                        $prediction,
                        $cartRecord->owner_type ?? null,
                        $cartRecord->owner_id ?? null,
                    );

                    Log::info('Queued recovery intervention', [
                        'cart_id' => $cart->getId(),
                        'strategy' => $strategy->id,
                        'probability' => $prediction->probability,
                        'risk_level' => $prediction->riskLevel,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Failed to analyze cart for abandonment', [
                    'cart_id' => $cartId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Analyze all potentially abandoned carts.
     */
    private function analyzeAbandonedCarts(
    ): void {
        if ($this->shouldFanOutByOwner()) {
            $this->dispatchPerOwner();

            return;
        }

        $owner = OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);

        OwnerContext::withOwner($owner, function () use ($owner): void {
            $predictor = app(AbandonmentPredictor::class);
            $optimizer = app(RecoveryOptimizer::class);
            $cartManager = app(CartManager::class);

            $highRiskCarts = $predictor->getHighRiskCarts($this->batchSize);

            $analyzed = 0;
            $interventionsQueued = 0;

            foreach ($highRiskCarts as $cartData) {
                try {
                    $cart = $cartManager
                        ->setIdentifier($cartData['identifier'])
                        ->setInstance($cartData['instance'] ?? 'default')
                        ->getCurrentCart();

                    $prediction = $predictor->predict($cart);

                    if ($prediction->needsIntervention()) {
                        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);
                        $this->queueIntervention(
                            $cart->getId(),
                            $strategy,
                            $prediction,
                            $cartData['owner_type'] ?? ($owner?->getMorphClass()),
                            $cartData['owner_id'] ?? ($owner?->getKey()),
                        );
                        $interventionsQueued++;
                    }

                    $analyzed++;
                } catch (Throwable $e) {
                    Log::warning('Failed to analyze cart', [
                        'cart_id' => $cartData['cart_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Completed abandonment analysis batch', [
                'carts_analyzed' => $analyzed,
                'interventions_queued' => $interventionsQueued,
            ]);
        });
    }

    /**
     * Queue an intervention for execution.
     */
    private function queueIntervention(
        string $cartId,
        RecoveryStrategy $strategy,
        AbandonmentPrediction $prediction,
        ?string $ownerType,
        string | int | null $ownerId
    ): void {
        $delay = now()->addMinutes($strategy->delayMinutes);

        ExecuteRecoveryIntervention::dispatch(
            cartId: $cartId,
            strategyId: $strategy->id,
            strategy: $strategy->toArray(),
            prediction: $prediction->toArray(),
            ownerType: $ownerType,
            ownerId: $ownerId,
        )
            ->delay($delay)
            ->onQueue('cart-recovery');

        $cartsTable = config('cart.database.table', 'carts');
        $query = DB::table($cartsTable)->where('id', $cartId);

        if ((bool) config('cart.owner.enabled', false)) {
            CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);
        }

        $query->update([
            'recovery_attempts' => DB::raw('recovery_attempts + 1'),
            'updated_at' => now(),
        ]);
    }

    private function shouldFanOutByOwner(): bool
    {
        if ($this->cartId !== null) {
            return false;
        }

        if (! (bool) config('cart.owner.enabled', false)) {
            return false;
        }

        return $this->ownerType === null && $this->ownerId === null;
    }

    private function dispatchPerOwner(): void
    {
        $cartsTable = config('cart.database.table', 'carts');
        $owners = DB::table($cartsTable)->select('owner_type', 'owner_id')->distinct()->get();

        if ($owners->isEmpty()) {
            return;
        }

        foreach ($owners as $row) {
            $ownerType = $this->normalizeOwnerValue($row->owner_type ?? null);
            $ownerId = $this->normalizeOwnerValue($row->owner_id ?? null);

            self::dispatch(
                cartId: null,
                batchSize: $this->batchSize,
                ownerType: $ownerType,
                ownerId: $ownerId,
            )->onQueue('cart-recovery');
        }
    }

    private function normalizeOwnerValue(mixed $value): string | int | null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) && (string) (int) $value === (string) $value
            ? (int) $value
            : (string) $value;
    }
}
