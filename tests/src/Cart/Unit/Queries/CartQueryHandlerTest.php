<?php

declare(strict_types=1);

use AIArmada\Cart\Queries\CartQueryHandler;
use AIArmada\Cart\Queries\GetAbandonedCartsQuery;
use AIArmada\Cart\Queries\GetCartSummaryQuery;
use AIArmada\Cart\Queries\SearchCartsQuery;
use AIArmada\Cart\ReadModels\CartReadModel;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

describe('CartQueryHandler', function (): void {
    beforeEach(function (): void {
        $this->queryBuilder = Mockery::mock(Builder::class);
        $this->queryBuilder->shouldReceive('where')->andReturnSelf();
        $this->queryBuilder->shouldReceive('whereNull')->andReturnSelf();
        $this->queryBuilder->shouldReceive('whereNotNull')->andReturnSelf();
        $this->queryBuilder->shouldReceive('whereBetween')->andReturnSelf();
        $this->queryBuilder->shouldReceive('having')->andReturnSelf();
        $this->queryBuilder->shouldReceive('select')->andReturnSelf();
        $this->queryBuilder->shouldReceive('selectRaw')->andReturnSelf();
        $this->queryBuilder->shouldReceive('from')->andReturnSelf();
        $this->queryBuilder->shouldReceive('join')->andReturnSelf();
        $this->queryBuilder->shouldReceive('leftJoin')->andReturnSelf();
        $this->queryBuilder->shouldReceive('groupBy')->andReturnSelf();
        $this->queryBuilder->shouldReceive('orderBy')->andReturnSelf();
        $this->queryBuilder->shouldReceive('limit')->andReturnSelf();
        $this->queryBuilder->shouldReceive('offset')->andReturnSelf();
        $this->queryBuilder->shouldReceive('first')->andReturn(null);
        $this->queryBuilder->shouldReceive('get')->andReturn(collect([]));
        $this->queryBuilder->shouldReceive('count')->andReturn(0);
        $this->queryBuilder->shouldReceive('sum')->andReturn(0);
        $this->queryBuilder->shouldReceive('avg')->andReturn(0);

        $this->connection = Mockery::mock(ConnectionInterface::class);
        $this->connection->shouldReceive('table')->andReturn($this->queryBuilder);

        $this->cache = new Repository(new ArrayStore);
        $this->readModel = new CartReadModel($this->connection, $this->cache);
        $this->handler = new CartQueryHandler($this->readModel);
    });

    it('can be instantiated', function (): void {
        expect($this->handler)->toBeInstanceOf(CartQueryHandler::class);
    });

    it('handles get summary query', function (): void {
        $query = new GetCartSummaryQuery('cart-123');

        $result = $this->handler->handleGetSummary($query);

        expect($result)->toBeNull(); // No cart in mock
    });

    it('handles get abandoned query', function (): void {
        $query = new GetAbandonedCartsQuery(
            olderThan: now()->subDays(1),
            minValueCents: 1000,
            limit: 50
        );

        $result = $this->handler->handleGetAbandoned($query);

        expect($result)->toBeArray();
    });

    it('handles search query', function (): void {
        $query = new SearchCartsQuery(
            identifier: 'user-123',
            limit: 25
        );

        $result = $this->handler->handleSearch($query);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('data')
            ->and($result)->toHaveKey('total');
    });

    it('gets cart details', function (): void {
        $result = $this->handler->getCartDetails('cart-456');

        expect($result)->toBeNull(); // No cart in mock
    });

    it('gets statistics', function (): void {
        $result = $this->handler->getStatistics(now()->subDays(7));

        expect($result)->toBeArray();
    });
});
