<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Services\CustomerResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class PersistCustomerStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'persist_customer';
    }

    public function getName(): string
    {
        return 'Persist Customer';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['process_payment'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $billable = $session->billable;

        if ($billable instanceof Model && ! $billable instanceof Customer) {
            return $this->success('Billable subject already resolved');
        }

        if (! class_exists(CustomerResolver::class) || ! class_exists(Customer::class)) {
            return $this->success('Customers package not available');
        }

        /** @var CustomerResolver $customerResolver */
        $customerResolver = app(CustomerResolver::class);
        $owner = $session->hasOwner() ? $session->owner : null;

        return $this->runWithinSessionOwnerContext($session, function () use ($customerResolver, $owner, $session): StepResult {
            $sessionCustomer = $session->customer_id !== null ? $session->customer : null;
            $customer = $customerResolver->resolve(
                user: $this->resolveActor($session),
                sessionCustomer: $sessionCustomer,
                billingData: $session->billing_data ?? [],
                shippingData: $session->shipping_data ?? [],
                owner: $owner,
            );

            if (! $customer instanceof Customer) {
                return $this->success('Proceeding without persisted customer');
            }

            $session->update([
                'customer_id' => $customer->id,
                'billable_type' => $customer->getMorphClass(),
                'billable_id' => (string) $customer->getKey(),
            ]);
            $session->unsetRelation('customer');
            $session->unsetRelation('billable');

            return $this->success('Customer persisted', [
                'customer_id' => $customer->id,
                'billable_type' => $customer->getMorphClass(),
                'billable_id' => (string) $customer->getKey(),
            ]);
        });
    }

    private function resolveActor(CheckoutSession $session): ?Model
    {
        $authenticatedUser = auth()->check() ? auth()->user() : null;

        if ($authenticatedUser instanceof Model) {
            return $authenticatedUser;
        }

        $storedActor = $this->resolveStoredActor($session);

        if ($storedActor instanceof Model) {
            return $storedActor;
        }

        $billable = $session->billable;

        if ($billable instanceof Model && ! $billable instanceof Customer) {
            return $billable;
        }

        return null;
    }

    private function resolveStoredActor(CheckoutSession $session): ?Model
    {
        $actorType = data_get($session->payment_data, 'checkout_actor.type');
        $actorId = data_get($session->payment_data, 'checkout_actor.id');

        if (! is_string($actorType) || $actorType === '') {
            return null;
        }

        if (! is_scalar($actorId) || (string) $actorId === '') {
            return null;
        }

        $actorModel = Relation::getMorphedModel($actorType) ?? $actorType;

        if (! is_string($actorModel) || ! class_exists($actorModel) || ! is_subclass_of($actorModel, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $actorModel */
        return $actorModel::query()->whereKey((string) $actorId)->first();
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runWithinSessionOwnerContext(CheckoutSession $session, callable $callback): mixed
    {
        return OwnerContext::withOwner($session->hasOwner() ? $session->owner : null, $callback);
    }
}
