<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Persistence;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Data\TransferInputData;
use AIArmada\Ticketing\Events\PassTransferred;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Features\Passes\Models\PassTransfer;
use AIArmada\Ticketing\Support\HolderAttributesValidator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use RuntimeException;

final readonly class PassTransferWriter implements PassTransferServiceInterface
{
    public function transfer(TransferInputData $input): string
    {
        return (new Pass)->getConnection()->transaction(function () use ($input): string {
            $pass = OwnerUiScope::apply(Pass::query(), includeGlobal: false)->whereKey($input->pass_id)->lockForUpdate()->first();

            if (! $pass instanceof Pass) {
                throw new AuthorizationException('The pass is not accessible in the current owner scope.');
            }

            if (! $this->isTransferable($pass)) {
                throw new RuntimeException('Pass cannot be transferred in its current state.');
            }

            if ($pass->owner_type === null && $pass->owner_id === null) {
                return OwnerContext::withOwner(null, fn (): string => $this->transferInput($pass, $input));
            }

            return $this->transferInput($pass, $input);
        });
    }

    private function transferInput(Pass $pass, TransferInputData $input): string
    {
        if ($input->existing_holder_id !== null) {
            $holder = PassHolder::query()->whereKey($input->existing_holder_id)->lockForUpdate()->first();

            if (! $holder instanceof PassHolder) {
                throw new AuthorizationException('The holder is not accessible in the current owner scope.');
            }
        } else {
            $holder = new PassHolder([
                'holder_type' => $input->holder_type,
                'holder_id' => $input->holder_id,
                'name' => $input->name,
                'email' => $input->email,
                'metadata' => $input->metadata,
            ]);
        }

        $this->assertHolderBelongsToPass($pass, $holder);
        HolderAttributesValidator::validateAttributes($holder->getAttributes());

        return (string) $this->transferLocked($pass, $holder, $input->reason)->getKey();
    }

    /**
     * Execute the holder switch. The pass row must already be locked; the
     * lock serializes concurrent transfers of the same pass.
     */
    private function transferLocked(Pass $freshPass, PassHolder $newHolder, ?string $reason): PassHolder
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
        $newHolder->transferred_at = null;
        $newHolder->save();

        $transfer = new PassTransfer;
        $transfer->pass_id = $freshPass->getKey();
        $transfer->from_holder_id = $previousHolder?->getKey();
        $transfer->to_holder_id = $newHolder->getKey();
        $transfer->reason = $reason;
        $transfer->owner_type = $freshPass->owner_type;
        $transfer->owner_id = $freshPass->owner_id;
        $transfer->save();

        $freshPass->getConnection()->afterCommit(fn () => event(new PassTransferred($freshPass, $previousHolder ?? $newHolder, $newHolder, $reason)));

        return $newHolder;
    }

    public function canTransfer(string $passId): bool
    {
        $pass = OwnerUiScope::apply(Pass::query(), includeGlobal: false)->find($passId);

        return $pass instanceof Pass && $this->isTransferable($pass);
    }

    private function isTransferable(Pass $pass): bool
    {
        if (! $pass->isValid()) {
            return false;
        }

        if ($pass->transfer_expires_at !== null && CarbonImmutable::now()->greaterThanOrEqualTo($pass->transfer_expires_at)) {
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
