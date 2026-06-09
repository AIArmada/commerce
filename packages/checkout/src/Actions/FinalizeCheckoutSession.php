<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Data\CheckoutResult;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class FinalizeCheckoutSession
{
    public function __construct(
        private Dispatcher $events,
    ) {}

    public function finalize(CheckoutSession $session): CheckoutResult
    {
        if (! $session->status->is(Completed::class)) {
            $session->transitionStatus(Completed::class);
        }

        $this->events->dispatch(new CheckoutCompleted($session));

        return CheckoutResult::success($session);
    }
}
