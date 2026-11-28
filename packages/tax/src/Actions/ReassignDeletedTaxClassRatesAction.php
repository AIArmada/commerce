<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxRate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final readonly class ReassignDeletedTaxClassRatesAction
{
    public function execute(TaxClass $class): void
    {
        if (config('tax.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            if ($owner === null) {
                if ($class->owner_type !== null || $class->owner_id !== null) {
                    throw new AuthorizationException('Cannot delete owned tax classes without an owner context.');
                }
            } elseif (! $class->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot delete tax classes outside the current owner scope.');
            }
        }

        $query = TaxRate::query()
            ->withoutOwnerScope()
            ->where('tax_class', $class->slug);

        if ($class->owner_type === null && $class->owner_id === null) {
            $query
                ->whereNull('owner_type')
                ->whereNull('owner_id');
        } else {
            $query
                ->where('owner_type', $class->owner_type)
                ->where('owner_id', $class->owner_id);
        }

        $replacementClassSlug = TaxClass::query()
            ->withoutOwnerScope()
            ->whereKeyNot($class->getKey())
            ->when(
                $class->owner_type === null && $class->owner_id === null,
                fn (Builder $builder): Builder => $builder
                    ->whereNull('owner_type')
                    ->whereNull('owner_id'),
                fn (Builder $builder): Builder => $builder
                    ->where('owner_type', $class->owner_type)
                    ->where('owner_id', $class->owner_id),
            )
            ->orderByDesc('is_default')
            ->orderBy('position')
            ->value('slug');

        $query->update(['tax_class' => $replacementClassSlug ?? 'standard']);
    }
}
