<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Actions\MergeOrderMetadata;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final readonly class RecordPromotionFulfillmentUsage
{
    public function handle(Order $order, ?string $ownerType, int | string | null $ownerId): void
    {
        $sessionId = $this->resolveSessionId($order);

        if ($sessionId === null) {
            $this->skip('order has no checkout session id', ['order_id' => $order->getKey()]);

            return;
        }
        $session = CheckoutSession::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->find($sessionId);

        if ($session === null) {
            $this->skip('checkout session was not found', ['order_id' => $order->getKey(), 'session_id' => $sessionId]);

            return;
        }

        if ((string) $session->getAttribute('owner_type') !== (string) $ownerType
            || (string) $session->getAttribute('owner_id') !== (string) $ownerId) {
            $this->skip('checkout session owner tuple does not match the order', [
                'order_id' => $order->getKey(),
                'session_id' => $sessionId,
            ]);

            return;
        }

        $allocations = $session->discount_data['allocations'] ?? [];

        if (! is_array($allocations) || $allocations === []) {
            $this->skip('checkout session contains no discount allocations', [
                'order_id' => $order->getKey(),
                'session_id' => $sessionId,
            ]);

            return;
        }

        try {
            $owner = OwnerTupleParser::fromTypeAndId($ownerType, $ownerId)->toOwnerModel();
        } catch (Throwable $exception) {
            $this->skip('order owner tuple is malformed', [
                'order_id' => $order->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }
        if ($order->getConnection()->getName() !== (new Promotion)->getConnection()->getName()) {
            throw new LogicException('Promotion usage and order metadata require the same database connection.');
        }

        $order->getConnection()->transaction(function () use ($order, $allocations, $owner, $ownerType, $ownerId): void {
            $lockedOrder = Order::query()
                ->withoutGlobalScope(OwnerScope::class)
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedOrder === null) {
                $this->skip('order no longer exists', ['order_id' => $order->getKey()]);

                return;
            }

            if ((string) $lockedOrder->getAttribute('owner_type') !== (string) $ownerType
                || (string) $lockedOrder->getAttribute('owner_id') !== (string) $ownerId) {
                $this->skip('locked order owner tuple does not match the event', ['order_id' => $lockedOrder->getKey()]);

                return;
            }

            if ($this->resolveSessionId($lockedOrder) !== $this->resolveSessionId($order)) {
                $this->skip('locked order checkout session does not match the event', ['order_id' => $lockedOrder->getKey()]);

                return;
            }

            $countedPromotionIds = $this->countedPromotionIds($lockedOrder);
            $previousCount = count($countedPromotionIds);

            $promotionIds = [];

            foreach ($allocations as $allocation) {
                if (! is_array($allocation)) {
                    continue;
                }

                if (($allocation['provider_key'] ?? '') !== 'promotions') {
                    Log::debug('Promotion usage skipped: allocation belongs to another provider.', [
                        'order_id' => $lockedOrder->getKey(),
                        'provider_key' => $allocation['provider_key'] ?? null,
                    ]);

                    continue;
                }

                $amount = $allocation['requested_amount'] ?? null;

                if (! is_int($amount) || $amount <= 0) {
                    $this->skip('promotion allocation has no positive allocated amount', ['order_id' => $lockedOrder->getKey()]);

                    continue;
                }

                $meta = $allocation['meta'] ?? null;
                $promotionId = is_array($meta) ? ($meta['promotion_id'] ?? null) : null;

                if (! is_string($promotionId) || $promotionId === '') {
                    $this->skip('promotion allocation has no promotion id', ['order_id' => $lockedOrder->getKey()]);

                    continue;
                }
                $promotionIds[$promotionId] = true;
            }

            // Acquire promotion write locks in a stable order across concurrent orders.
            $promotionIds = array_keys($promotionIds);
            sort($promotionIds, SORT_STRING);

            foreach ($promotionIds as $promotionId) {
                if (in_array($promotionId, $countedPromotionIds, true)) {
                    continue;
                }

                $includeGlobal = ! config('promotions.features.owner.enabled', false)
                    || (bool) config('promotions.features.owner.include_global', false);

                $promotion = OwnerQuery::applyToEloquentBuilder(
                    Promotion::query()->withoutGlobalScope(OwnerScope::class),
                    $owner,
                    $includeGlobal,
                )->find($promotionId);

                if (! $promotion instanceof Promotion) {
                    $this->skip('promotion allocation could not be resolved in the order owner scope', [
                        'order_id' => $lockedOrder->getKey(),
                        'promotion_id' => $promotionId,
                    ]);

                    continue;
                }

                $promotionOwner = $promotion->owner_type === null ? null : $owner;
                $incremented = OwnerContext::withOwner($promotionOwner, static fn (): bool => $promotion->tryIncrementUsage());

                if (! $incremented) {
                    $this->skip('promotion usage limit was reached during atomic increment', [
                        'order_id' => $lockedOrder->getKey(),
                        'promotion_id' => $promotionId,
                    ]);

                    continue;
                }

                $countedPromotionIds[] = $promotionId;
            }

            if (count($countedPromotionIds) > $previousCount) {
                $this->persistCountedPromotionIds($lockedOrder, $owner, $countedPromotionIds);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function countedPromotionIds(Order $order): array
    {
        $metadata = $order->metadata;

        if (! is_array($metadata)) {
            return [];
        }

        $counted = $metadata['promotions_usage_counted'] ?? [];

        if (! is_array($counted)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $counted,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));
    }

    /**
     * @param  list<string>  $countedPromotionIds
     */
    private function persistCountedPromotionIds(Order $order, ?Model $owner, array $countedPromotionIds): void
    {
        OwnerContext::withOwner($owner, static function () use ($order, $countedPromotionIds): void {
            app(MergeOrderMetadata::class)->handle((string) $order->getKey(), [
                'promotions_usage_counted' => $countedPromotionIds,
            ]);
        });
    }

    private function resolveSessionId(Order $order): ?string
    {
        $metadata = $order->metadata;

        if (! is_array($metadata)) {
            return null;
        }

        $sessionId = $metadata['checkout_session_id'] ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function skip(string $reason, array $context): void
    {
        Log::warning('Promotion usage allocation skipped.', ['reason' => $reason, ...$context]);
    }
}
