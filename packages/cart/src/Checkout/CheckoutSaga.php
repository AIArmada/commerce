<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\Exceptions\CheckoutException;
use AIArmada\Cart\Checkout\Stages\FulfillmentStage;
use AIArmada\Cart\Checkout\Stages\PaymentStage;
use AIArmada\Cart\Checkout\Stages\ReservationStage;
use AIArmada\Cart\Checkout\Stages\ValidationStage;
use AIArmada\Cart\Contracts\CartValidatorInterface;
use Illuminate\Support\Facades\DB;

/**
 * Checkout saga orchestrating the complete checkout process.
 *
 * Provides a fluent API for configuring and executing checkout:
 * - Validation → Reservation → Payment → Fulfillment
 *
 * Implements saga pattern with automatic compensation on failure.
 */
final class CheckoutSaga
{
    private Cart $cart;

    private ValidationStage $validationStage;

    private ReservationStage $reservationStage;

    private PaymentStage $paymentStage;

    private FulfillmentStage $fulfillmentStage;

    /**
     * @var array<CheckoutStageInterface>
     */
    private array $customStages = [];

    /**
     * @var array<string, mixed>
     */
    private array $context = [];

    private bool $useTransaction = true;

    public function __construct(Cart $cart)
    {
        $this->cart = $cart;
        $this->validationStage = new ValidationStage;
        $this->reservationStage = new ReservationStage;
        $this->paymentStage = new PaymentStage;
        $this->fulfillmentStage = new FulfillmentStage;
    }

    /**
     * Create a new saga for a cart.
     */
    public static function for(Cart $cart): self
    {
        return new self($cart);
    }

    /**
     * Add a validator for the validation stage.
     */
    public function addValidator(CartValidatorInterface $validator): self
    {
        $this->validationStage->addValidator($validator);

        return $this;
    }

    /**
     * Configure inventory reservation.
     *
     * @param  callable(string $itemId, int $quantity, Cart $cart): bool  $reserve
     * @param  callable(string $itemId, int $quantity, Cart $cart): void  $release
     */
    public function withInventory(callable $reserve, callable $release): self
    {
        $this->reservationStage
            ->onReserve($reserve)
            ->onRelease($release);

        return $this;
    }

    /**
     * Configure payment processing.
     *
     * @param  callable(Cart $cart, array $context): array{success: bool, transaction_id?: string, payment_url?: string, message?: string}  $process
     * @param  callable(string $transactionId, int $amountCents): bool|null  $refund
     */
    public function withPayment(callable $process, ?callable $refund = null): self
    {
        $this->paymentStage->onProcess($process);

        if ($refund !== null) {
            $this->paymentStage->onRefund($refund);
        }

        return $this;
    }

    /**
     * Configure order fulfillment.
     *
     * @param  callable(Cart $cart, array $context): array{order_id: string, order_number?: string}  $create
     * @param  callable(string $orderId): void|null  $cancel
     */
    public function withFulfillment(callable $create, ?callable $cancel = null): self
    {
        $this->fulfillmentStage->onCreateOrder($create);

        if ($cancel !== null) {
            $this->fulfillmentStage->onCancelOrder($cancel);
        }

        return $this;
    }

    /**
     * Add a custom stage after a specific stage.
     */
    public function addStageAfter(string $afterStage, CheckoutStageInterface $stage): self
    {
        $this->customStages[] = [
            'after' => $afterStage,
            'stage' => $stage,
        ];

        return $this;
    }

    /**
     * Set initial context data.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Disable database transaction wrapping.
     */
    public function withoutTransaction(): self
    {
        $this->useTransaction = false;

        return $this;
    }

    /**
     * Execute the checkout saga.
     *
     * @throws CheckoutException
     */
    public function execute(): CheckoutResult
    {
        $pipeline = $this->buildPipeline();

        if ($this->useTransaction) {
            return DB::transaction(fn () => $pipeline->execute());
        }

        return $pipeline->execute();
    }

    /**
     * Build the checkout pipeline with all stages.
     */
    private function buildPipeline(): CheckoutPipeline
    {
        $pipeline = new CheckoutPipeline($this->cart);
        $pipeline->withContext($this->context);

        // Add standard stages with custom stages inserted at correct positions
        $this->addStageWithCustom($pipeline, $this->validationStage, 'validation');
        $this->addStageWithCustom($pipeline, $this->reservationStage, 'reservation');
        $this->addStageWithCustom($pipeline, $this->paymentStage, 'payment');
        $this->addStageWithCustom($pipeline, $this->fulfillmentStage, 'fulfillment');

        return $pipeline;
    }

    /**
     * Add a stage and any custom stages that should follow it.
     */
    private function addStageWithCustom(
        CheckoutPipeline $pipeline,
        CheckoutStageInterface $stage,
        string $stageName
    ): void {
        $pipeline->addStage($stage);

        foreach ($this->customStages as $customStage) {
            if ($customStage['after'] === $stageName) {
                $pipeline->addStage($customStage['stage']);
            }
        }
    }
}
