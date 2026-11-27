<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SyncCatalogRelationships
{
    /** @param array<int, mixed> $ids */
    public function syncAttributeGroups(Attribute $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = Attribute::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $ids = $this->normalizeIds($ids, 'groups');
            $allowed = AttributeGroup::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'groups');
            $locked->groups()->sync($ids);
        }, 3);
    }

    /** @param array<int, mixed> $ids */
    public function syncGroupAttributes(AttributeGroup $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = AttributeGroup::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $ids = $this->normalizeIds($ids, 'groupAttributes');
            $allowed = Attribute::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'groupAttributes');
            $locked->groupAttributes()->sync($ids);
        }, 3);
    }

    /** @param array<int, mixed> $ids */
    public function syncSetAttributes(AttributeSet $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = AttributeSet::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $ids = $this->normalizeIds($ids, 'setAttributes');
            $allowed = Attribute::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'setAttributes');
            $locked->setAttributes()->sync($ids);
        }, 3);
    }

    /** @param array<int, mixed> $ids */
    public function syncSetGroups(AttributeSet $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = AttributeSet::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $ids = $this->normalizeIds($ids, 'groups');
            $allowed = AttributeGroup::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'groups');
            $locked->groups()->sync($ids);
        }, 3);
    }

    /** @param array<int, mixed> $ids */
    public function syncCollectionProducts(Collection $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = Collection::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isManual()) {
                throw ValidationException::withMessages(['products' => 'Only manual collections accept explicit product membership.']);
            }

            $ids = $this->normalizeIds($ids, 'products');
            $allowed = Product::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'products');
            $locked->products()->sync($ids);
        }, 3);
    }

    /** @param array<int, mixed> $ids */
    public function syncProductCategories(Product $record, array $ids): void
    {
        DB::transaction(function () use ($record, $ids): void {
            $owner = OwnerContext::resolve();

            if (config('products.features.owner.enabled', false)) {
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
            }

            $locked = Product::query()->forOwner($owner, false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $ids = $this->normalizeIds($ids, 'categories');
            $allowed = Category::query()->forOwner()->whereKey($ids)->pluck('id')->all();
            $this->assertAllowed($ids, $allowed, 'categories');
            $locked->categories()->sync($ids);
        }, 3);
    }

    /**
     * @param array<int, mixed> $ids
     * @return list<string>
     */
    private function normalizeIds(array $ids, string $field): array
    {
        foreach ($ids as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                throw ValidationException::withMessages([$field => 'Invalid catalog record identifier.']);
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param list<string> $ids @param array<int, mixed> $allowed */
    private function assertAllowed(array $ids, array $allowed, string $field): void
    {
        if (count($ids) !== count($allowed)) {
            throw ValidationException::withMessages([$field => 'One or more selected records are invalid for the current owner scope.']);
        }
    }
}
