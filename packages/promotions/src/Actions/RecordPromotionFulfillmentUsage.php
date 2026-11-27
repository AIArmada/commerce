<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

                $promotionId = $allocation['meta']['promotion_id'] ?? null;

                if (! is_string($promotionId) || $promotionId === '') {
                    $this->skip('promotion allocation has no promotion id', ['order_id' => $lockedOrder->getKey()]);

                    continue;
                }
                if (in_array((string) $promotionId, $countedPromotionIds, true)) {
                    continue;
                }

                $promotion = Promotion::query()
                    ->forOwner($owner, false)
                    ->find($promotionId);

                if ($promotion === null) {
                    $this->skip('promotion allocation could not be resolved in the order owner scope', [
                        'order_id' => $lockedOrder->getKey(),
                        'promotion_id' => $promotionId,
                    ]);

                    continue;
                }

                $incremented = OwnerContext::withOwner($owner, static fn (): bool => $promotion->tryIncrementUsage());

                if (! $incremented) {
                    $this->skip('promotion usage limit was reached during atomic increment', [
                        'order_id' => $lockedOrder->getKey(),
                        'promotion_id' => $promotionId,
                    ]);

                    continue;
                }

                $countedPromotionIds[] = (string) $promotionId;
            }

            if (count($countedPromotionIds) > $previousCount) {
                $this->persistCountedPromotionIds($lockedOrder, $owner, $countedPromotionIds);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function countedPromotionIds(mixed $order): array
    {
        $metadata = is_callable([$order, 'getAttribute'])
            ? $order->getAttribute('metadata')
            : null;

        if (! is_array($metadata)) {
            return [];
        }

        $counted = $metadata['promotions_usage_counted'] ?? [];

        if (! is_array($counted)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $id): string => (string) $id, $counted),
            static fn (string $id): bool => $id !== '',
        ));
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

    private function resolveSessionId(mixed $order): ?string
    {
        $metadata = is_callable([$order, 'getAttribute'])
            ? $order->getAttribute('metadata')
            : null;

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
