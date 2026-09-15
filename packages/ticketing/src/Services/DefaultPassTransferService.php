<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class DefaultPassTransferService implements PassTransferServiceInterface
{
    public function transfer(Pass $pass, PassHolder $newHolder, ?string $reason = null): PassHolder
    {
        if (! $this->canTransfer($pass)) {
            throw new RuntimeException('Pass cannot be transferred in its current state.');
        }

        $this->assertHolderBelongsToPass($pass, $newHolder);

        return DB::transaction(function () use ($pass, $newHolder, $reason) {
            $freshPass = Pass::query()->whereKey($pass->getKey())->lockForUpdate()->first();

            if (! $freshPass instanceof Pass) {
                throw new AuthorizationException('The pass is not accessible in the current owner scope.');
            }

            if (! $this->canTransfer($freshPass)) {
                throw new RuntimeException('Pass cannot be transferred in its current state.');
            }

            // Global passes transfer in explicit global context so the new
            // holder and transfer rows stay global instead of inheriting
            // whatever ambient owner context the transfer runs in. Owned
            // passes bind their own owner explicitly below, and the owner
            // guards fail closed when the ambient context does not match.
            if ($freshPass->owner_type === null && $freshPass->owner_id === null) {
                return OwnerContext::withOwner(null, fn (): PassHolder => $this->transferLocked($pass, $freshPass, $newHolder, $reason));
            }

            return $this->transferLocked($pass, $freshPass, $newHolder, $reason);
        });
    }

    /**
     * Execute the holder switch. The pass row must already be locked; the
     * lock serializes concurrent transfers of the same pass.
     */
    private function transferLocked(Pass $pass, Pass $freshPass, PassHolder $newHolder, ?string $reason): PassHolder
    {
        // The new holder arrives unsaved and non-current. Persist it as
        // non-current first so the previous-holder lookup below can only
        // match genuinely previous rows, then flip it after unsetting them.
        $newHolder->pass_id = $freshPass->getKey();
        $newHolder->is_current = false;

        if (! $newHolder->exists) {
            // Child rows inherit the pass owner explicitly so the holder can
            // never land in a different owner scope than its pass. (Existing
            // holders were already equality-checked in
            // assertHolderBelongsToPass and keep their immutable tuple.)
            $newHolder->owner_type = $freshPass->owner_type;
            $newHolder->owner_id = $freshPass->owner_id;
        }

        $newHolder->save();

        $previousHolders = PassHolder::query()
            ->where('pass_id', $freshPass->getKey())
            ->whereKeyNot($newHolder->getKey())
            ->where('is_current', true)
            ->lockForUpdate()
            ->orderBy('created_at')
            ->get();

        $previousHolder = $previousHolders->first();
        $now = CarbonImmutable::now();

        foreach ($previousHolders as $holder) {
            $holder->is_current = false;
            $holder->transferred_at = $now;
            $holder->save();
        }

        $newHolder->is_current = true;
        $newHolder->save();

        $transfer = new PassTransfer;
        $transfer->pass_id = $freshPass->getKey();
        $transfer->from_holder_id = $previousHolder?->getKey();
        $transfer->to_holder_id = $newHolder->getKey();
        $transfer->reason = $reason;
        $transfer->owner_type = $freshPass->owner_type;
        $transfer->owner_id = $freshPass->owner_id;
        $transfer->save();

        event(new PassTransferred($pass, $previousHolder ?? $newHolder, $newHolder, $reason));

        return $newHolder;
    }

    public function canTransfer(Pass $pass): bool
    {
        if (! $pass->isValid()) {
            return false;
        }

        if ($pass->transfer_expires_at !== null && CarbonImmutable::now()->isAfter($pass->transfer_expires_at)) {
            return false;
        }

        return true;
    }

    private function assertHolderBelongsToPass(Pass $pass, PassHolder $newHolder): void
    {
        if (! $newHolder->exists) {
            return;
        }

        if ($newHolder->pass_id !== null && (string) $newHolder->pass_id !== (string) $pass->getKey()) {
            throw new InvalidArgumentException('The holder already belongs to a different pass.');
        }

        if (config('ticketing.owner.enabled', true)
            && ($newHolder->owner_type !== $pass->owner_type || (string) $newHolder->owner_id !== (string) $pass->owner_id)
        ) {
            throw new AuthorizationException('The holder is not accessible in the current owner scope.');
        }
    }
}
