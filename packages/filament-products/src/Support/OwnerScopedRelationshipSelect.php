<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use Closure;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

final class OwnerScopedRelationshipSelect
{
    /**
     * Build an owner-scope guard for a multiple relationship select.
     *
     * Filament validates submitted IDs against the option labels it rendered,
     * but that check is presentation-bound: a tampered request can submit any
     * ID. This callback runs in the component's
     * saveRelationshipsBeforeChildren() slot — inside the same transaction and
     * immediately before the relationship sync — and re-validates every
     * submitted ID against the current owner scope, failing with a
     * ValidationException keyed to the component's state path.
     *
     * @param  class-string<Model>  $modelClass
     * @return Closure(Component): void
     */
    public static function guard(string $modelClass): Closure
    {
        return static function (Component $component) use ($modelClass): void {
            $state = $component->getState();

            OwnerScopedIds::ensureAllowed(
                $component->getStatePath(),
                $modelClass,
                is_array($state) ? $state : [],
            );
        };
    }
}
