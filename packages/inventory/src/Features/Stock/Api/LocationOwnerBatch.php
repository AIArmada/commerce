<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface LocationOwnerBatch
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return list<TResult>
     */
    public function forEach(callable $callback): array;
}
