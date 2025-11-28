<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\Chip\Facades\ChipCollect;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * CHIP Checkout wrapper class.
 *
 * Creates CHIP purchases that redirect the customer to CHIP's checkout page.
 */
class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * The owner of the checkout session.
     *
     * @var Billable|\Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * The CHIP purchase data (checkout session).
     */
    protected array $purchase;

    /**
     * Create a new checkout instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @return void
     */
    public function __construct($owner, array $purchase)
    {
        $this->owner = $owner;
        $this->purchase = $purchase;
    }

    /**
     * Dynamically get values from the purchase data.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->purchase[$key] ?? null;
    }

    /**
     * Begin a new guest checkout session.
     */
    public static function guest(): CheckoutBuilder
    {
        return new CheckoutBuilder();
    }

    /**
     * Begin a new customer checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     */
    public static function customer($owner): CheckoutBuilder
    {
        return new CheckoutBuilder($owner);
    }

    /**
     * Create a new checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  int  $amount  Amount in cents
     */
    public static function create($owner, int $amount, array $options = []): self
    {
        $purchaseData = [
            'products' => $options['products'] ?? [[
                'name' => $options['reference'] ?? 'Payment',
                'price' => $amount / 100, // Convert cents to decimal
                'quantity' => 1,
            ]],
            'currency' => $options['currency'] ?? config('cashier-chip.currency', 'MYR'),
            'send_receipt' => $options['send_receipt'] ?? true,
            'success_callback' => $options['success_url'] ?? config('cashier-chip.success_url', url('/checkout/success')),
            'failure_callback' => $options['cancel_url'] ?? config('cashier-chip.cancel_url', url('/checkout/cancel')),
            'callback_url' => $options['webhook_url'] ?? config('cashier-chip.webhook_url'),
        ];

        // Add client information if owner exists
        if ($owner) {
            $purchaseData['client'] = [
                'email' => $owner->email ?? null,
                'full_name' => $owner->name ?? null,
                'phone' => $owner->phone ?? null,
            ];

            // Add CHIP client ID if exists
            if ($chipId = $owner->chipId()) {
                $purchaseData['client']['id'] = $chipId;
            }
        }

        // Add recurring token request if specified
        if ($options['recurring'] ?? false) {
            $purchaseData['send_recurring_token'] = true;
        }

        // Add reference
        if (isset($options['reference'])) {
            $purchaseData['reference'] = $options['reference'];
        }

        // Merge any additional metadata
        if (isset($options['metadata'])) {
            $purchaseData['metadata'] = $options['metadata'];
        }

        // Create the purchase via CHIP API
        $purchase = ChipCollect::createPurchase($purchaseData);

        return new static($owner, $purchase);
    }

    /**
     * Get the checkout URL.
     */
    public function url(): ?string
    {
        return $this->purchase['checkout_url'] ?? null;
    }

    /**
     * Get the purchase ID.
     */
    public function id(): ?string
    {
        return $this->purchase['id'] ?? null;
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->url(), 303);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $this->redirect();
    }

    /**
     * Get the owner of the checkout session.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function owner()
    {
        return $this->owner;
    }

    /**
     * Get the underlying CHIP purchase data.
     */
    public function asChipPurchase(): array
    {
        return $this->purchase;
    }

    /**
     * Convert to a Payment instance.
     */
    public function asPayment(): Payment
    {
        return new Payment($this->purchase);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return $this->purchase;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
