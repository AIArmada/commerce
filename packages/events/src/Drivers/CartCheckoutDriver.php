<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Events\Contracts\EventCartDriver;
use AIArmada\Events\Data\EventCartLineData;
use AIArmada\Events\Platform\CommerceIntegration;
use AIArmada\Ticketing\Features\TicketTypes\Actions\AutoAddRequiredTicketBundlesAction;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class CartCheckoutDriver implements EventCartDriver
{
    public function __construct(
        private readonly ?CartManagerInterface $cartManager = null,
        private readonly ?CheckoutServiceInterface $checkoutService = null,
        private readonly ?AutoAddRequiredTicketBundlesAction $requiredBundles = null,
    ) {}

    public function currentCartId(): ?string
    {
        return $this->cartId($this->cartManager()->getCurrentCart());
    }

    public function instanceCartId(string $name, ?string $identifier = null): ?string
    {
        return $this->cartId($this->cartManager()->getCartInstance($name, $identifier));
    }

    public function addLine(Cart $cart, EventCartLineData $line): void
    {
        if ($cart->has($line->ticketTypeId)) {
            $updated = $cart->update($line->ticketTypeId, [
                'quantity' => ['value' => $line->quantity],
                'attributes' => $line->attributes,
            ]);

            if ($updated !== null) {
                return;
            }
        }

        $cart->add(
            id: $line->ticketTypeId,
            name: $line->name,
            price: $line->price,
            quantity: $line->quantity,
            attributes: $line->attributes,
            associatedModel: $this->associatedModel($line),
        );
    }

    public function addRequiredBundles(Cart $cart, string $ticketTypeId): void
    {
        if (! CommerceIntegration::aiArmadaCheckoutAvailable() || $this->requiredBundles === null) {
            return;
        }

        $ticketType = TicketType::query()->find($ticketTypeId);

        if (! $ticketType instanceof TicketType) {
            return;
        }

        $this->requiredBundles->handle($cart, $ticketType);
    }

    public function startCheckout(string $cartId, ?string $customerId = null): mixed
    {
        return $this->checkoutService()->startCheckout(cartId: $cartId, customerId: $customerId);
    }

    private function cartManager(): CartManagerInterface
    {
        return $this->cartManager ?? throw new RuntimeException('The AIArmada cart integration is unavailable.');
    }

    private function checkoutService(): CheckoutServiceInterface
    {
        return $this->checkoutService ?? throw new RuntimeException('The AIArmada checkout integration is unavailable.');
    }

    private function cartId(Cart $cart): ?string
    {
        $id = $cart->getId();

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function associatedModel(EventCartLineData $line): ?Model
    {
        $modelClass = $line->associatedClass;

        if ($modelClass === null || $line->associatedId === null || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $model = $modelClass::query()->find($line->associatedId);

        return $model instanceof Model ? $model : null;
    }
}
