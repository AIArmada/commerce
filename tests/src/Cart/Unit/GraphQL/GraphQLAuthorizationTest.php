<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\GraphQL\Mutations\CartMutations;
use AIArmada\Cart\GraphQL\Queries\CartQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

describe('Cart GraphQL resolver authorization', function (): void {
    it('rejects all calls when GraphQL is disabled', function (): void {
        config()->set('cart.graphql.enabled', false);

        $mutations = app(CartMutations::class);

        expect(fn () => $mutations->clearCart(null, [
            'identifier' => 'anything',
            'instance' => 'default',
        ]))->toThrow(\RuntimeException::class);
    });

    it('requires authentication when GraphQL is enabled', function (): void {
        config()->set('cart.graphql.enabled', true);

        $queries = app(CartQuery::class);

        expect(fn () => $queries->cartByIdentifier(null, [
            'identifier' => 'someone-else',
            'instance' => 'default',
        ]))->toThrow(AuthenticationException::class);
    });

    it('rejects identifier mismatch even when authenticated', function (): void {
        config()->set('cart.graphql.enabled', true);

        $user = createUserWithRoles();
        Auth::setUser($user);

        $queries = app(CartQuery::class);

        expect(fn () => $queries->cartByIdentifier(null, [
            'identifier' => 'not-the-current-user',
            'instance' => 'default',
        ]))->toThrow(AuthorizationException::class);
    });
});
