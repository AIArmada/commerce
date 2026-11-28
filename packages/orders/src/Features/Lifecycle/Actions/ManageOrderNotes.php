<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\OrderNotes;
use AIArmada\Orders\Models\Order;
use InvalidArgumentException;

final readonly class ManageOrderNotes implements OrderNotes
{
    use AssertsOrderOwnerBoundary;

    public function add(string $orderId, string $content, string $visibility, ?string $actorId = null): string
    {
        $this->validate($content, $visibility);

        return (new Order)->getConnection()->transaction(function () use ($orderId, $content, $visibility, $actorId): string {
            $order = $this->lockOrder($orderId);

            return (string) $order->orderNotes()->create([
                'content' => $content,
                'visibility' => $visibility,
                'user_id' => $actorId,
            ])->getKey();
        });
    }

    public function edit(string $orderId, string $noteId, string $content, string $visibility): void
    {
        $this->validate($content, $visibility);

        (new Order)->getConnection()->transaction(function () use ($orderId, $noteId, $content, $visibility): void {
            $order = $this->lockOrder($orderId);
            $note = $order->orderNotes()->lockForUpdate()->findOrFail($noteId);
            $note->update(['content' => $content, 'visibility' => $visibility]);
        });
    }

    public function remove(string $orderId, array $noteIds): void
    {
        (new Order)->getConnection()->transaction(function () use ($orderId, $noteIds): void {
            $order = $this->lockOrder($orderId);

            foreach (array_unique($noteIds) as $noteId) {
                $order->orderNotes()->lockForUpdate()->findOrFail($noteId)->delete();
            }
        });
    }

    private function lockOrder(string $orderId): Order
    {
        $order = Order::query()->forOwner()->lockForUpdate()->findOrFail($orderId);
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order;
    }

    private function validate(string $content, string $visibility): void
    {
        if (mb_trim($content) === '') {
            throw new InvalidArgumentException('Order note content is required.');
        }

        if (! in_array($visibility, ['internal', 'customer'], true)) {
            throw new InvalidArgumentException('Invalid order note visibility.');
        }
    }
}
