<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class EnsurePriceListBySlug
{
    /** @param array<string, mixed> $attributes */
    public function execute(string $slug, array $attributes): PriceList
    {
        return DB::transaction(function () use ($slug, $attributes): PriceList {
            $query = PriceList::query();
            $owner = null;

            if (config('pricing.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $list = (clone $query)->where('slug', $slug)->lockForUpdate()->first();

            if ($list === null) {
                try {
                    $list = DB::transaction(function () use ($slug, $attributes, $owner): PriceList {
                        $candidate = new PriceList(['slug' => $slug, 'name' => $attributes['name']]);

                        if ($owner !== null) {
                            $candidate->assignOwner($owner);
                        }

                        if (! $candidate->save()) {
                            throw new RuntimeException('Pricing record write was cancelled.');
                        }

                        return $candidate;
                    });
                } catch (UniqueConstraintViolationException $exception) {
                    $list = (clone $query)->where('slug', $slug)->lockForUpdate()->first();

                    if ($list === null) {
                        throw $exception;
                    }
                }
            }

            $list->fill(Arr::except($attributes, ['id', 'slug', 'owner_type', 'owner_id']));

            if ($list->isDirty() && ! $list->save()) {
                throw new RuntimeException('Pricing record write was cancelled.');
            }

            return $list;
        }, 3);
    }
}
