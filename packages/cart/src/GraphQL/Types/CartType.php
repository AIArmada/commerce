<?php

declare(strict_types=1);

namespace AIArmada\Cart\GraphQL\Types;

/**
 * GraphQL Cart type definition and resolver.
 *
 * Provides type definition and field resolvers for the Cart type.
 * Can be used with Lighthouse, Webonyx GraphQL, or other GraphQL libraries.
 */
final class CartType
{
    /**
     * Get the type definition for Cart.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'name' => 'Cart',
            'description' => 'A shopping cart containing items and conditions',
            'fields' => [
                'id' => [
                    'type' => 'ID!',
                    'description' => 'Unique cart identifier (UUID)',
                ],
                'identifier' => [
                    'type' => 'String!',
                    'description' => 'Cart identifier (user ID, session ID, etc.)',
                ],
                'instance' => [
                    'type' => 'String!',
                    'description' => 'Cart instance name (default, wishlist, etc.)',
                ],
                'items' => [
                    'type' => '[CartItem!]!',
                    'description' => 'Items in the cart',
                ],
                'itemCount' => [
                    'type' => 'Int!',
                    'description' => 'Number of unique items in cart',
                ],
                'totalQuantity' => [
                    'type' => 'Int!',
                    'description' => 'Total quantity of all items',
                ],
                'conditions' => [
                    'type' => '[CartCondition!]!',
                    'description' => 'Conditions applied to the cart',
                ],
                'subtotal' => [
                    'type' => 'Money!',
                    'description' => 'Subtotal before conditions',
                ],
                'total' => [
                    'type' => 'Money!',
                    'description' => 'Total after all conditions',
                ],
                'savings' => [
                    'type' => 'Money!',
                    'description' => 'Total savings from discounts',
                ],
                'metadata' => [
                    'type' => 'JSON',
                    'description' => 'Custom metadata attached to cart',
                ],
                'version' => [
                    'type' => 'Int!',
                    'description' => 'Cart version for optimistic locking',
                ],
                'createdAt' => [
                    'type' => 'DateTime!',
                    'description' => 'When the cart was created',
                ],
                'updatedAt' => [
                    'type' => 'DateTime!',
                    'description' => 'When the cart was last updated',
                ],
            ],
        ];
    }

    /**
     * Get the GraphQL SDL (Schema Definition Language) for Cart.
     */
    public static function sdl(): string
    {
        return <<<'GRAPHQL'
"""
A shopping cart containing items and conditions
"""
type Cart {
    "Unique cart identifier (UUID)"
    id: ID!
    
    "Cart identifier (user ID, session ID, etc.)"
    identifier: String!
    
    "Cart instance name (default, wishlist, etc.)"
    instance: String!
    
    "Items in the cart"
    items: [CartItem!]!
    
    "Number of unique items in cart"
    itemCount: Int!
    
    "Total quantity of all items"
    totalQuantity: Int!
    
    "Conditions applied to the cart"
    conditions: [CartCondition!]!
    
    "Subtotal before conditions"
    subtotal: Money!
    
    "Total after all conditions"
    total: Money!
    
    "Total savings from discounts"
    savings: Money!
    
    "Custom metadata attached to cart"
    metadata: JSON
    
    "Cart version for optimistic locking"
    version: Int!
    
    "When the cart was created"
    createdAt: DateTime!
    
    "When the cart was last updated"
    updatedAt: DateTime!
}

"""
An item in the shopping cart
"""
type CartItem {
    "Unique item identifier"
    id: ID!
    
    "Item name"
    name: String!
    
    "Unit price"
    price: Money!
    
    "Quantity in cart"
    quantity: Int!
    
    "Line item subtotal (price * quantity)"
    subtotal: Money!
    
    "Item-specific conditions"
    conditions: [CartCondition!]!
    
    "Custom item attributes"
    attributes: JSON
}

"""
A condition (discount, tax, fee) applied to cart or item
"""
type CartCondition {
    "Condition name"
    name: String!
    
    "Condition type (discount, tax, fee, shipping)"
    type: String!
    
    "Condition value (e.g., '-10%', '5.00')"
    value: String!
    
    "Calculated monetary value"
    calculatedValue: Money!
    
    "Whether this is a discount"
    isDiscount: Boolean!
    
    "Whether the value is a percentage"
    isPercentage: Boolean!
    
    "Sort order"
    order: Int!
}

"""
Money amount with currency
"""
type Money {
    "Amount in smallest currency unit (cents)"
    amount: Int!
    
    "Currency code (e.g., USD, MYR)"
    currency: String!
    
    "Human-readable formatted amount"
    formatted: String!
}
GRAPHQL;
    }
}
