<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Data\TransferInputData;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Support\HolderAttributesValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class TransferPassToHolderAction
{
    public function __construct(private PassTransferServiceInterface $transfers) {}

    public function handle(
        Pass $pass,
        PassHolder | Model | null $newHolder,
        array $holderAttributes = [],
        ?string $reason = null,
        ?Model $authorizedBy = null,
    ): PassHolder {
        return $pass->getConnection()->transaction(function () use ($pass, $newHolder, $holderAttributes, $reason, $authorizedBy): PassHolder {
            $lockedPass = Pass::query()->whereKey($pass->getKey())->lockForUpdate()->first();

            if (! $lockedPass instanceof Pass) {
                throw new AuthorizationException('The pass is not accessible in the current owner scope.');
            }

            if ($authorizedBy !== null) {
                Gate::forUser($authorizedBy)->authorize('transfer', $lockedPass);
            }

            $resolvedHolder = $this->resolveHolder($lockedPass, $newHolder, $holderAttributes);

            $holderId = $this->transfers->transfer(new TransferInputData(
                pass_id: (string) $lockedPass->getKey(),
                holder_type: $resolvedHolder->holder_type,
                holder_id: $resolvedHolder->holder_id === null ? null : (string) $resolvedHolder->holder_id,
                name: $resolvedHolder->name,
                email: $resolvedHolder->email,
                reason: $reason,
                existing_holder_id: $resolvedHolder->exists ? (string) $resolvedHolder->getKey() : null,
                metadata: $resolvedHolder->metadata,
            ));

            $resolve = fn (): PassHolder => PassHolder::query()->where('pass_id', $lockedPass->getKey())->where('is_current', true)->findOrFail($holderId);

            return $lockedPass->owner_type === null && $lockedPass->owner_id === null
                ? OwnerContext::withOwner(null, $resolve)
                : $resolve();
        });
    }

    /** @param array<string, mixed> $holderAttributes */
    private function resolveHolder(
        Pass $pass,
        PassHolder | Model | null $newHolder,
        array $holderAttributes = [],
    ): PassHolder {
        if ($newHolder instanceof PassHolder) {
            if ($newHolder->exists
                && $newHolder->pass_id !== null
                && (string) $newHolder->pass_id !== (string) $pass->getKey()
            ) {
                throw new InvalidArgumentException('The holder already belongs to a different pass.');
            }

            return $newHolder;
        }

        if ($newHolder instanceof Model) {
            HolderAttributesValidator::validateHolderModel($newHolder);

            $holder = new PassHolder;
            $holder->pass_id = $pass->getKey();
            $holder->holder_type = $newHolder->getMorphClass();
            $holder->holder_id = $newHolder->getKey();
            $holder->name = $newHolder->getAttribute('name');
            $holder->email = $newHolder->getAttribute('email');
            // Unsaved and non-current: the transfer service owns the holder
            // switch (previous lookup, owner binding, current flip).
            $holder->is_current = false;

            return $holder;
        }

        HolderAttributesValidator::validateAttributes($holderAttributes);

        $holder = new PassHolder;
        $holder->pass_id = $pass->getKey();
        $holder->name = $holderAttributes['name'] ?? null;
        $holder->email = $holderAttributes['email'] ?? null;

        if (isset($holderAttributes['holder_type'], $holderAttributes['holder_id'])) {
            $holder->holder_type = $holderAttributes['holder_type'];
            $holder->holder_id = $holderAttributes['holder_id'];
        }

        // Unsaved and non-current: the transfer service owns the holder switch.
        $holder->is_current = false;

        return $holder;
    }
}
