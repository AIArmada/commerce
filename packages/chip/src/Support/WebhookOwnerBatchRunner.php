<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Models\Webhook;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use Illuminate\Database\Eloquent\Model;

final class WebhookOwnerBatchRunner
{
    /**
     * @param  callable(?Model, ?int=): array{processed: int, succeeded: int, failed: int}  $callback
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function run(callable $callback, int $limit = 0): array
    {
        if (! (bool) config('chip.owner.enabled', false) || OwnerContext::resolve() !== null) {
            return $callback(OwnerContext::resolve());
        }

        $owners = Webhook::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->orderBy('owner_type')
            ->orderBy('owner_id')
            ->get();

        if ($owners->isEmpty()) {
            return OwnerContext::withOwner(null, fn (): array => $callback(null));
        }

        $totals = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ];

        foreach ($owners as $row) {
            if ($limit > 0 && $totals['processed'] >= $limit) {
                break;
            }

            $remainingLimit = $limit > 0 ? $limit - $totals['processed'] : 0;
            $owner = OwnerTupleParser::fromRow($row, OwnerTupleColumns::forModelClass(Webhook::class))->toOwnerModel();

            $result = OwnerContext::withOwner($owner, fn () => $callback($owner, $remainingLimit > 0 ? $remainingLimit : null));

            $totals['processed'] += $result['processed'];
            $totals['succeeded'] += $result['succeeded'];
            $totals['failed'] += $result['failed'];
        }

        return $totals;
    }
}
