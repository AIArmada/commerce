<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

<<<<<<< Updated upstream
<<<<<<< Updated upstream
use AIArmada\Chip\Facades\ChipCollect;
=======
use AIArmada\Chip\DataObjects\Purchase;
>>>>>>> Stashed changes
=======
use AIArmada\Chip\DataObjects\Purchase;
>>>>>>> Stashed changes
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;
<<<<<<< Updated upstream
<<<<<<< Updated upstream
use ReturnTypeWillChange;
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes

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
<<<<<<< Updated upstream
<<<<<<< Updated upstream
     * The CHIP purchase data (checkout session).
=======
     * The CHIP purchase instance.
>>>>>>> Stashed changes
=======
     * The CHIP purchase instance.
>>>>>>> Stashed changes
     */
    protected Purchase $purchase;

    /**
     * Create a new checkout instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
<<<<<<< Updated upstream
<<<<<<< Updated upstream
     * @return void
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
     */
    public function __construct($owner, Purchase $purchase)
    {
        $this->owner = $owner;
        $this->purchase = $purchase;
    }

    /**
<<<<<<< Updated upstream
<<<<<<< Updated upstream
     * Dynamically get values from the purchase data.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->purchase[$key] ?? null;
    }

    /**
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
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
<<<<<<< Updated upstream
<<<<<<< Updated upstream
=======
     * @param  array<string, mixed>  $options
>>>>>>> Stashed changes
=======
     * @param  array<string, mixed>  $options
>>>>>>> Stashed changes
     */
    public static function create($owner, int $amount, array $options = []): self
    {
        $builder = CashierChip::chip()->purchase()
            ->currency($options['currency'] ?? config('cashier-chip.currency', 'MYR'));

        // Add products
        if (isset($options['products'])) {
            foreach ($options['products'] as $product) {
                $builder->addProduct(
                    $product['name'],
                    $product['price'],
                    $product['quantity'] ?? 1
                );
            }
        } else {
            $builder->addProduct(
                $options['reference'] ?? 'Payment',
                $amount
            );
        }

        // Add client information if owner exists
        if ($owner) {
            if (method_exists($owner, 'chipId') && $owner->chipId()) {
                $builder->clientId($owner->chipId());
            } else {
                $builder->customer(
                    email: $owner->email ?? '',
                    fullName: $owner->name ?? null,
                    phone: $owner->phone ?? null
                );
            }
        }

        // Add redirect URLs
        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['cancel_url'])) {
            $builder->failureUrl($options['cancel_url']);
        }

        if (isset($options['webhook_url'])) {
            $builder->webhook($options['webhook_url']);
        }

        // Configure receipt
        if (isset($options['send_receipt'])) {
            $builder->sendReceipt($options['send_receipt']);
        }

        // Add recurring token request if specified
        if ($options['recurring'] ?? false) {
            $builder->forceRecurring(true);
        }

        // Add reference
        if (isset($options['reference'])) {
            $builder->reference($options['reference']);
        }

        // Merge any additional metadata
        if (isset($options['metadata'])) {
            $builder->metadata($options['metadata']);
        }

        $purchase = $builder->create();

        return new static($owner, $purchase);
    }

    /**
     * Get the checkout URL.
     */
    public function url(): ?string
    {
        return $this->purchase->getCheckoutUrl();
    }

    /**
     * Get the purchase ID.
     */
    public function id(): string
    {
        return $this->purchase->id;
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->url() ?? '', 303);
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
<<<<<<< Updated upstream
<<<<<<< Updated upstream
     * Get the underlying CHIP purchase data.
=======
     * Get the underlying CHIP purchase.
>>>>>>> Stashed changes
=======
     * Get the underlying CHIP purchase.
>>>>>>> Stashed changes
     */
    public function asChipPurchase(): Purchase
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
<<<<<<< Updated upstream
=======
     *
     * @return array<string, mixed>
<<<<<<< Updated upstream
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
     */
    public function toArray(): array
    {
        return $this->purchase->toArray();
    }

    /**
     * Convert the object to its JSON representation.
<<<<<<< Updated upstream
<<<<<<< Updated upstream
     *
     * @param  int  $options
=======
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
<<<<<<< Updated upstream
=======
     *
     * @return array<string, mixed>
<<<<<<< Updated upstream
>>>>>>> Stashed changes
=======
>>>>>>> Stashed changes
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
<<<<<<< Updated upstream
=======

    /**
     * Dynamically get values from the purchase.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->purchase->{$key} ?? null;
    }
>>>>>>> Stashed changes
}
