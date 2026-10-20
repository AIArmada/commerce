<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Aggregate-child ownership contract.
 *
 * A child row (option, option value, attribute value, variant) may only be
 * created when its owner tuple matches the aggregate parent's, including the
 * global-only (null/null) tuple. Otherwise the parent's cascade can never see
 * the child again, leaving dangling descendants behind.
 */
final class ChildOwnershipGuard
{
    /**
     * @param  string  $relationship  Parent label used in the failure messages, e.g. "product".
     */
    public static function apply(Model $child, Model $parent, string $relationship): void
    {
        $childHasOwner = $child->getAttribute('owner_type') !== null || $child->getAttribute('owner_id') !== null;
        $parentIsGlobal = $parent->getAttribute('owner_type') === null && $parent->getAttribute('owner_id') === null;

        if ($childHasOwner) {
            if ($parentIsGlobal || ! self::sameTuple($child, $parent)) {
                throw new InvalidArgumentException(sprintf(
                    'Cross-tenant write blocked: child owner does not match its %s owner.',
                    $relationship,
                ));
            }

            return;
        }

        if (! $parentIsGlobal) {
            if (! self::autoAssignOnCreate()) {
                throw new InvalidArgumentException(sprintf(
                    'Owner assignment is disabled. Supply the parent owner tuple explicitly so the child shares its %s owner.',
                    $relationship,
                ));
            }

            $child->setAttribute('owner_type', $parent->getAttribute('owner_type'));
            $child->setAttribute('owner_id', $parent->getAttribute('owner_id'));

            return;
        }

        if (OwnerContext::isExplicitGlobal()) {
            return;
        }

        if (self::autoAssignOnCreate()) {
            throw new InvalidArgumentException(sprintf(
                'Cross-tenant write blocked: child owner does not match its %s owner. Global parents require global children.',
                $relationship,
            ));
        }

        throw new AuthorizationException(sprintf(
            'Explicit global owner context is required to create global children of a %s. Use OwnerContext::withOwner(null, ...).',
            $relationship,
        ));
    }

    private static function sameTuple(Model $child, Model $parent): bool
    {
        return $child->getAttribute('owner_type') === $parent->getAttribute('owner_type')
            && (string) $child->getAttribute('owner_id') === (string) $parent->getAttribute('owner_id');
    }

    private static function autoAssignOnCreate(): bool
    {
        return (bool) config('products.features.owner.auto_assign_on_create', true);
    }
}
