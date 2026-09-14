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

            $session->persistState([
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
        if (! $this->actorModelAllowed($actorModel)) {
            return null;
        }

        $actor = $actorModel::query()->whereKey((string) $actorId)->first();

        if (! $actor instanceof Model) {
            return null;
        }

        return $this->actorOwnerConsistent($session, $actor) ? $actor : null;
    }

    /**
     * Only explicitly allowed actor models may be resolved from the stored
     * reference. The stored type comes from session JSON, so resolving an
     * arbitrary model class would let crafted input bind any user-like row
     * as the checkout actor.
     *
     * @param  class-string<Model>  $actorModel
     */
    private function actorModelAllowed(string $actorModel): bool
    {
        $allowed = config('checkout.checkout_actor.allowed_types', []);
        $allowed = is_array($allowed) ? array_values($allowed) : [];

        $providers = config('auth.providers', []);

        if (is_array($providers)) {
            foreach ($providers as $provider) {
                $model = is_array($provider) ? ($provider['model'] ?? null) : null;

                if (is_string($model) && $model !== '') {
                    $allowed[] = $model;
                }
            }
        }

        $customerModel = config('checkout.models.customer');

        if (is_string($customerModel) && $customerModel !== '') {
            $allowed[] = $customerModel;
        }

        foreach ($allowed as $allowedModel) {
            if (! is_string($allowedModel) || $allowedModel === '') {
                continue;
            }

            if ($actorModel === $allowedModel || is_subclass_of($actorModel, $allowedModel)) {
                return true;
            }
        }

        return false;
    }

    private function actorOwnerConsistent(CheckoutSession $session, Model $actor): bool
    {
        $attributes = $actor->getAttributes();

        if (! array_key_exists('owner_type', $attributes) || ! array_key_exists('owner_id', $attributes)) {
            return true;
        }

        $actorOwnerType = $actor->getAttribute('owner_type');
        $actorOwnerId = $actor->getAttribute('owner_id');

        if ($actorOwnerType === null || $actorOwnerId === null) {
            return true;
        }

        if ($session->owner_type === null || $session->owner_id === null) {
            return true;
        }

        return $actorOwnerType === $session->owner_type
            && (string) $actorOwnerId === (string) $session->owner_id;
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
