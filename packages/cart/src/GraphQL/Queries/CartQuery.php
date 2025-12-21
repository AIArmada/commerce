<?php

declare(strict_types=1);

namespace AIArmada\Cart\GraphQL\Queries;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Queries\CartQueryHandler;
use AIArmada\Cart\Queries\GetAbandonedCartsQuery;
use AIArmada\Cart\Queries\GetCartSummaryQuery;
use AIArmada\Cart\Queries\SearchCartsQuery;
use DateTimeImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * GraphQL Query resolvers for Cart.
 *
 * Provides query resolvers that can be used with Lighthouse or other GraphQL libraries.
 */
final class CartQuery
{
    public function __construct(
        private readonly CartManagerInterface $cartManager,
        private readonly CartQueryHandler $queryHandler
    ) {}

    /**
     * Get the query SDL.
     */
    public static function sdl(): string
    {
        return <<<'GRAPHQL'
extend type Query {
    "Get cart by ID"
    cart(id: ID!): Cart
    
    "Get cart by identifier and instance"
    cartByIdentifier(identifier: String!, instance: String = "default"): Cart
    
    "Get current user's cart"
    myCart(instance: String = "default"): Cart
    
    "Get abandoned carts (admin)"
    abandonedCarts(
        olderThan: DateTime!
        minValueCents: Int
        limit: Int = 50
    ): [Cart!]!
    
    "Search carts (admin)"
    searchCarts(
        identifier: String
        instance: String
        createdAfter: DateTime
        createdBefore: DateTime
        minItems: Int
        limit: Int = 50
        offset: Int = 0
    ): CartSearchResult!
}

type CartSearchResult {
    data: [Cart!]!
    total: Int!
}
GRAPHQL;
    }

    /**
     * Resolve cart by ID.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    public function cart(mixed $root, array $args): ?array
    {
        $this->ensureGraphQlEnabled();
        $user = $this->requireAuthenticatedUser();

        $query = new GetCartSummaryQuery($args['id']);

        $summary = $this->queryHandler->handleGetSummary($query);

        if ($summary === null) {
            return null;
        }

        if ((string) ($summary['identifier'] ?? '') !== (string) $user->getAuthIdentifier()) {
            return null;
        }

        return $summary;
    }

    /**
     * Resolve cart by identifier and instance.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|null
     */
    public function cartByIdentifier(mixed $root, array $args): ?array
    {
        $this->ensureGraphQlEnabled();
        $user = $this->requireAuthenticatedUser();
        $this->assertIdentifierMatchesUser($args['identifier'] ?? null, $user);

        $cart = $this->cartManager
            ->setIdentifier($args['identifier'])
            ->setInstance($args['instance'] ?? 'default')
            ->getCurrentCart();

        if ($cart->isEmpty()) {
            return null;
        }

        return $this->transformCart($cart);
    }

    /**
     * Resolve current user's cart.
     *
     * @param  array<string, mixed>  $args
     * @param  mixed  $context  GraphQL context containing user info
     * @return array<string, mixed>|null
     */
    public function myCart(mixed $root, array $args, mixed $context = null): ?array
    {
        $this->ensureGraphQlEnabled();

        $user = $this->resolveUser($context);

        if ($user === null) {
            throw new AuthenticationException('Authentication required.');
        }

        $cart = $this->cartManager
            ->setIdentifier((string) $user->id)
            ->setInstance($args['instance'] ?? 'default')
            ->getCurrentCart();

        return $this->transformCart($cart);
    }

    /**
     * Resolve abandoned carts.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, array<string, mixed>>
     */
    public function abandonedCarts(mixed $root, array $args): array
    {
        $this->ensureGraphQlEnabled();
        $this->ensureAdminQueriesEnabled();

        $olderThan = new DateTimeImmutable($args['olderThan']);

        $query = new GetAbandonedCartsQuery(
            olderThan: $olderThan,
            minValueCents: $args['minValueCents'] ?? null,
            limit: $args['limit'] ?? 50
        );

        return $this->queryHandler->handleGetAbandoned($query);
    }

    /**
     * Search carts.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function searchCarts(mixed $root, array $args): array
    {
        $this->ensureGraphQlEnabled();
        $this->ensureAdminQueriesEnabled();

        $query = new SearchCartsQuery(
            identifier: $args['identifier'] ?? null,
            instance: $args['instance'] ?? null,
            createdAfter: isset($args['createdAfter']) ? new DateTimeImmutable($args['createdAfter']) : null,
            createdBefore: isset($args['createdBefore']) ? new DateTimeImmutable($args['createdBefore']) : null,
            minItems: $args['minItems'] ?? null,
            limit: $args['limit'] ?? 50,
            offset: $args['offset'] ?? 0
        );

        return $this->queryHandler->handleSearch($query);
    }

    /**
     * Transform cart to GraphQL response format.
     *
     * @return array<string, mixed>
     */
    private function transformCart(\AIArmada\Cart\Cart $cart): array
    {
        $currency = config('cart.money.default_currency', 'MYR');

        return [
            'id' => $cart->getId(),
            'identifier' => $cart->getIdentifier(),
            'instance' => $cart->instance(),
            'items' => $cart->getItems()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => [
                    'amount' => $item->price,
                    'currency' => $currency,
                    'formatted' => $item->getPrice()->format(),
                ],
                'quantity' => $item->quantity,
                'subtotal' => [
                    'amount' => $item->getRawSubtotal(),
                    'currency' => $currency,
                    'formatted' => $item->getSubtotal()->format(),
                ],
                'conditions' => [],
                'attributes' => $item->attributes->toArray(),
            ])->values()->toArray(),
            'itemCount' => $cart->countItems(),
            'totalQuantity' => $cart->getTotalQuantity(),
            'conditions' => $cart->getConditions()->map(fn ($condition) => [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'value' => $condition->getValue(),
                'calculatedValue' => [
                    'amount' => 0,
                    'currency' => $currency,
                    'formatted' => '0.00',
                ],
                'isDiscount' => $condition->getType() === 'discount',
                'isPercentage' => str_contains($condition->getValue(), '%'),
                'order' => $condition->getOrder(),
            ])->values()->toArray(),
            'subtotal' => [
                'amount' => $cart->getRawSubtotal(),
                'currency' => $currency,
                'formatted' => $cart->subtotal()->format(),
            ],
            'total' => [
                'amount' => $cart->getRawTotal(),
                'currency' => $currency,
                'formatted' => $cart->total()->format(),
            ],
            'savings' => [
                'amount' => 0,
                'currency' => $currency,
                'formatted' => '0.00',
            ],
            'metadata' => $cart->getAllMetadata(),
            'version' => $cart->getVersion(),
            'createdAt' => $cart->getCreatedAt(),
            'updatedAt' => $cart->getUpdatedAt(),
        ];
    }

    /**
     * Resolve user from GraphQL context.
     */
    private function resolveUser(mixed $context): ?object
    {
        if ($context === null) {
            return Auth::user();
        }

        if (is_object($context) && method_exists($context, 'user')) {
            return $context->user();
        }

        if (is_array($context) && isset($context['user'])) {
            return $context['user'];
        }

        return null;
    }

    private function ensureGraphQlEnabled(): void
    {
        if (! (bool) config('cart.graphql.enabled', false)) {
            throw new RuntimeException('Cart GraphQL is disabled.');
        }
    }

    private function ensureAdminQueriesEnabled(): void
    {
        $user = $this->requireAuthenticatedUser();

        if (! (bool) config('cart.graphql.admin_queries_enabled', false)) {
            throw new AuthorizationException('Cart GraphQL admin queries are disabled.');
        }

        // Minimal safety net: when enabled, still require a logged-in user.
        // Fine-grained authorization should be implemented by the host app.
        if (! method_exists($user, 'getAuthIdentifier')) {
            throw new AuthorizationException('User model is not authenticatable.');
        }
    }

    private function requireAuthenticatedUser(): object
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Authentication required.');
        }

        return $user;
    }

    private function assertIdentifierMatchesUser(mixed $identifier, object $user): void
    {
        if (! is_string($identifier) || $identifier === '') {
            throw new AuthorizationException('Identifier is required.');
        }

        if (! method_exists($user, 'getAuthIdentifier')) {
            throw new AuthorizationException('User model is not authenticatable.');
        }

        if ((string) $identifier !== (string) $user->getAuthIdentifier()) {
            throw new AuthorizationException('Identifier does not match the authenticated user.');
        }
    }
}
