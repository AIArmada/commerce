<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Catalog;

use AIArmada\Affiliates\Contracts\PromotableProviderInterface;
use Closure;

/**
 * Promotable provider backed by a callback.
 *
 * Lets merchants expose catalog items without writing a provider class:
 *
 *     $registry->register(new CallbackPromotableProvider('product',
 *         fn (?string $programId): iterable => [[
 *             'subject_key' => 'SKU-1',
 *             'title' => 'Example product',
 *             'url' => 'https://example.com/products/sku-1',
 *         }],
 *     ));
 */
final class CallbackPromotableProvider implements PromotableProviderInterface
{
    /**
     * @param  Closure(?string): iterable  $list
     */
    public function __construct(
        private readonly string $type,
        private readonly Closure $list,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function list(?string $programId = null): iterable
    {
        return ($this->list)($programId);
    }
}
