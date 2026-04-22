<?php

declare(strict_types=1);

namespace AIArmada\Checkout\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Abstract base class for all checkout session states.
 *
 * State Diagram:
 *
 *                    ┌─────────┐
 *                    │ PENDING │
 *                    └────┬────┘
 *                         │
 *                         ▼
 *                  ┌─────────────┐
 *         ┌───────▶│ PROCESSING  │◀───────┐
 *         │        └──────┬──────┘        │
 *         │               │               │
 *         │               ▼               │
 *         │     ┌─────────────────┐       │
 *         │     │AWAITING_PAYMENT │       │
 *         │     └────────┬────────┘       │
 *         │              │                │
 *         │     ┌────────┴────────┐       │
 *         │     ▼                 ▼       │
 *  ┌──────┴──────┐       ┌───────────────┐│
 *  │PAYMENT_FAILED│◀────▶│PAYMENT_PROCESS││
 *  └──────┬──────┘       └───────┬───────┘│
 *         │                      │        │
 *         │ (retry)              ▼        │
 *         └───────┐       ┌───────────┐   │
 *                 │       │ COMPLETED │   │
 *                 │       └───────────┘   │
 *                 │                       │
 *         ┌───────┴───────┐       ┌───────┴───┐
 *         │   CANCELLED   │       │  EXPIRED  │
 *         └───────────────┘       └───────────┘
 */
abstract class CheckoutState extends State
{
    /**
     * Get the display color for Filament badges.
     */
    abstract public function color(): string;

    /**
     * Get the heroicon name for display.
     */
    abstract public function icon(): string;

    /**
     * Get the translatable label.
     */
    abstract public function label(): string;

    /**
     * Configure all allowed state transitions.
     */
    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            // Initial flow
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Pending::class, PaymentFailed::class)
            ->allowTransition(Pending::class, Cancelled::class)
            ->allowTransition(Pending::class, Expired::class)
            // Processing flow
            ->allowTransition(Processing::class, AwaitingPayment::class)
            ->allowTransition(Processing::class, PaymentProcessing::class)
            ->allowTransition(Processing::class, PaymentFailed::class)
            ->allowTransition(Processing::class, Completed::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Processing::class, Expired::class)
            // Awaiting payment outcomes
            ->allowTransition(AwaitingPayment::class, Processing::class)
            ->allowTransition(AwaitingPayment::class, PaymentProcessing::class)
            ->allowTransition(AwaitingPayment::class, PaymentFailed::class)
            ->allowTransition(AwaitingPayment::class, Completed::class)
            ->allowTransition(AwaitingPayment::class, Cancelled::class)
            ->allowTransition(AwaitingPayment::class, Expired::class)
            // Payment processing outcomes
            ->allowTransition(PaymentProcessing::class, Processing::class)
            ->allowTransition(PaymentProcessing::class, AwaitingPayment::class)
            ->allowTransition(PaymentProcessing::class, Completed::class)
            ->allowTransition(PaymentProcessing::class, PaymentFailed::class)
            // Payment retry flow
            ->allowTransition(PaymentFailed::class, Processing::class)
            ->allowTransition(PaymentFailed::class, AwaitingPayment::class)
            ->allowTransition(PaymentFailed::class, PaymentProcessing::class)
            ->allowTransition(PaymentFailed::class, Cancelled::class);
    }

    /**
     * Whether the checkout session can be cancelled in this state.
     */
    public function canCancel(): bool
    {
        return false;
    }

    /**
     * Whether the checkout session can be modified in this state.
     */
    public function canModify(): bool
    {
        return false;
    }

    /**
     * Whether a payment retry is allowed in this state.
     */
    public function canRetryPayment(): bool
    {
        return false;
    }

    /**
     * Whether this is a terminal/final state.
     */
    public function isTerminal(): bool
    {
        return false;
    }

    /**
     * Get the state name (e.g., 'pending', 'completed').
     */
    final public function name(): string
    {
        return $this->getValue();
    }

    /**
     * Determine whether the current state matches the given class.
     *
     * @param  class-string<CheckoutState>  $stateClass
     */
    final public function is(string $stateClass): bool
    {
        return $this instanceof $stateClass;
    }
}
