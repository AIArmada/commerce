<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Targeting;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Context object containing all information needed for targeting evaluation.
 */
readonly class TargetingContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Cart $cart,
        public ?Model $user = null,
        public ?Request $request = null,
        public array $metadata = [],
    ) {}

    /**
     * Create context from cart with auto-resolved user and request.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function fromCart(Cart $cart, array $metadata = []): self
    {
        $user = null;
        $request = null;

        if (function_exists('auth') && auth()->check()) {
            $user = auth()->user();
        }

        if (function_exists('request')) {
            $request = request();
        }

        return new self($cart, $user, $request, $metadata);
    }

    /**
     * Get user segments/groups.
     *
     * @return array<string>
     */
    public function getUserSegments(): array
    {
        if ($this->user === null) {
            return ['guest'];
        }

        // Try common segment methods/properties
        if (method_exists($this->user, 'getSegments')) {
            return $this->user->getSegments();
        }

        if (property_exists($this->user, 'segments') || isset($this->user->segments)) {
            $segments = $this->user->segments;

            return is_array($segments) ? $segments : [];
        }

        // Check for roles (Spatie Permission)
        if (method_exists($this->user, 'getRoleNames')) {
            return $this->user->getRoleNames()->toArray();
        }

        return [];
    }

    /**
     * Get a user attribute value.
     */
    public function getUserAttribute(string $attribute): mixed
    {
        if ($this->user === null) {
            return null;
        }

        if (method_exists($this->user, 'getAttribute')) {
            return $this->user->getAttribute($attribute);
        }

        return $this->user->{$attribute} ?? null;
    }

    /**
     * Check if this is the user's first purchase.
     */
    public function isFirstPurchase(): bool
    {
        if ($this->user === null) {
            return true; // Guests are always "first purchase"
        }

        // Check metadata first
        if (isset($this->metadata['is_first_purchase'])) {
            return (bool) $this->metadata['is_first_purchase'];
        }

        // Check via model attribute
        $isFirstPurchase = $this->getUserAttribute('is_first_purchase');
        if ($isFirstPurchase !== null) {
            return (bool) $isFirstPurchase;
        }

        // Try to check via order count
        if (method_exists($this->user, 'orders')) {
            return $this->user->orders()->count() === 0;
        }

        // Check via total_orders attribute
        $totalOrders = $this->getUserAttribute('total_orders');

        if ($totalOrders !== null) {
            return (int) $totalOrders === 0;
        }

        return false;
    }

    /**
     * Get customer lifetime value in minor units.
     */
    public function getCustomerLifetimeValue(): int
    {
        if ($this->user === null) {
            return 0;
        }

        // Check metadata first
        if (isset($this->metadata['clv'])) {
            return (int) $this->metadata['clv'];
        }

        // Try common CLV methods
        if (method_exists($this->user, 'getLifetimeValue')) {
            return (int) $this->user->getLifetimeValue();
        }

        $clv = $this->getUserAttribute('customer_lifetime_value')
            ?? $this->getUserAttribute('lifetime_value')
            ?? $this->getUserAttribute('clv')
            ?? $this->getUserAttribute('total_spent');

        return (int) ($clv ?? 0);
    }

    /**
     * Get cart subtotal in minor units.
     */
    public function getCartValue(): int
    {
        return $this->cart->getRawSubtotalWithoutConditions();
    }

    /**
     * Get total quantity of items in cart.
     */
    public function getCartQuantity(): int
    {
        return $this->cart->getItems()->sum(fn (CartItem $item) => $item->quantity);
    }

    /**
     * Get product SKUs/IDs in cart.
     *
     * @return array<string>
     */
    public function getProductIdentifiers(): array
    {
        return $this->cart->getItems()
            ->map(function (CartItem $item): ?string {
                // Try to get SKU from item attributes first
                $sku = $item->getAttribute('sku');
                if ($sku !== null) {
                    return (string) $sku;
                }

                // Try associated model
                $model = $item->associatedModel;
                if ($model === null) {
                    return $item->id;
                }

                if (method_exists($model, 'getSku')) {
                    return $model->getSku();
                }

                if (is_object($model) && property_exists($model, 'sku')) {
                    return $model->sku;
                }

                return $item->id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get categories of products in cart.
     *
     * @return array<string>
     */
    public function getProductCategories(): array
    {
        return $this->cart->getItems()
            ->flatMap(function (CartItem $item): array {
                // Try to get category from item attributes first
                $category = $item->getAttribute('category');
                if ($category !== null) {
                    return is_array($category) ? $category : [(string) $category];
                }

                // Try associated model
                $model = $item->associatedModel;
                if ($model === null) {
                    return [];
                }

                // Try common category methods
                if (method_exists($model, 'getCategories')) {
                    return $model->getCategories();
                }

                if ($model instanceof Model && method_exists($model, 'categories')) {
                    $categories = $model->getRelationValue('categories');

                    if ($categories instanceof \Illuminate\Support\Collection) {
                        return $categories->pluck('slug')->all();
                    }
                }

                if (is_object($model) && property_exists($model, 'category')) {
                    return [$model->category];
                }

                if (is_object($model) && property_exists($model, 'category_id')) {
                    return [(string) $model->category_id];
                }

                return [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the current channel (web, mobile, api, pos, etc).
     */
    public function getChannel(): string
    {
        // Check metadata first
        if (isset($this->metadata['channel'])) {
            return (string) $this->metadata['channel'];
        }

        // Check request header
        if ($this->request !== null) {
            $channel = $this->request->header('X-Channel')
                ?? $this->request->header('X-Sales-Channel');

            if ($channel !== null) {
                return is_array($channel) ? $channel[0] : $channel;
            }
        }

        return 'web';
    }

    /**
     * Get the device type (desktop, mobile, tablet).
     */
    public function getDevice(): string
    {
        // Check metadata first
        if (isset($this->metadata['device'])) {
            return (string) $this->metadata['device'];
        }

        if ($this->request === null) {
            return 'desktop';
        }

        $userAgent = $this->request->userAgent() ?? '';

        // Check for tablet first (iPads, Android tablets)
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        // Check for mobile devices (smartphones)
        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|wpdesktop/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Get the country code (ISO 3166-1 alpha-2).
     */
    public function getCountry(): ?string
    {
        // Check metadata first
        if (isset($this->metadata['country'])) {
            return (string) $this->metadata['country'];
        }

        // Check request header
        if ($this->request !== null) {
            $country = $this->request->header('CF-IPCountry')  // Cloudflare
                ?? $this->request->header('X-Country')
                ?? $this->request->header('X-Geo-Country');

            if ($country !== null) {
                return is_array($country) ? $country[0] : $country;
            }
        }

        // Check user country
        if ($this->user !== null) {
            $country = $this->getUserAttribute('country')
                ?? $this->getUserAttribute('country_code');

            if ($country !== null) {
                return (string) $country;
            }
        }

        return null;
    }

    /**
     * Get the referrer URL or source.
     */
    public function getReferrer(): ?string
    {
        // Check metadata first
        if (isset($this->metadata['referrer'])) {
            return (string) $this->metadata['referrer'];
        }

        if ($this->request !== null) {
            $referer = $this->request->header('Referer');
            if ($referer !== null) {
                return is_array($referer) ? $referer[0] : $referer;
            }
        }

        return null;
    }

    /**
     * Get current time in the specified or detected timezone.
     */
    public function getCurrentTime(?string $timezone = null): Carbon
    {
        $tz = $timezone ?? $this->getTimezone();

        return Carbon::now($tz);
    }

    /**
     * Get the timezone for time-based rules.
     */
    public function getTimezone(): string
    {
        // Check metadata first
        if (isset($this->metadata['timezone'])) {
            return (string) $this->metadata['timezone'];
        }

        // Check user timezone
        if ($this->user !== null) {
            $tz = $this->getUserAttribute('timezone');

            if ($tz !== null) {
                return (string) $tz;
            }
        }

        return config('app.timezone', 'UTC');
    }

    /**
     * Get custom metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
