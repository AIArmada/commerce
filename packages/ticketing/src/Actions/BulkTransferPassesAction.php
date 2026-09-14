<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Exceptions\BulkTransferSizeExceededException;
use AIArmada\Ticketing\Jobs\BulkSendTransferNotificationsJob;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class BulkTransferPassesAction
{
    use AsAction;

    /** @param array<int, string> $passIds */
    public function handle(
        array $passIds,
        PassHolder | Model | null $newHolder,
        ?string $reason = null,
        array $holderAttributes = [],
        ?Model $authorizedBy = null,
    ): Collection {
        $maxSize = config('ticketing.transfers.bulk_max_size', 100);

        if ($passIds === []) {
            throw new InvalidArgumentException('At least one pass ID is required for bulk transfer.');
        }

        if (count($passIds) > $maxSize) {
            throw new BulkTransferSizeExceededException($maxSize, count($passIds));
        }

        $requestedPassIds = array_values(array_unique($passIds));
        $passes = Pass::query()->whereIn('id', $requestedPassIds)->get();
        $missingPassIds = array_values(array_diff($requestedPassIds, $passes->modelKeys()));

        if ($missingPassIds !== []) {
            throw (new ModelNotFoundException)->setModel(Pass::class, $missingPassIds);
        }

        $this->assertSingleOwner($passes);

        return DB::transaction(function () use ($passes, $newHolder, $reason, $holderAttributes, $authorizedBy) {
            $previousHolders = PassHolder::query()
                ->whereIn('pass_id', $passes->modelKeys())
                ->where('is_current', true)
                ->get();

            $action = resolve(TransferPassToHolderAction::class);
            $newHolders = new Collection;

            foreach ($passes as $pass) {
                $newHolders->push(
                    $action->handle($pass, $this->holderForPass($newHolder), $holderAttributes, $reason, $authorizedBy)
                );
            }

            $event = new PassesBulkTransferred($passes, $newHolders->first(), $reason, previousHolders: $previousHolders);
            $ownerType = $passes->first()?->owner_type;
            $ownerId = $passes->first()?->owner_id;

            dispatch(new BulkSendTransferNotificationsJob(
                event: $event,
                ownerType: $ownerType,
                ownerId: $ownerId,
                ownerIsGlobal: $ownerType === null && $ownerId === null,
            ))->afterCommit();

            return $newHolders;
        });
    }

    /**
     * Each pass needs its own holder row: replicate a PassHolder template so
     * sequential transfers never re-point (steal) a single persisted row.
     */
    private function holderForPass(PassHolder | Model | null $newHolder): PassHolder | Model | null
    {
        if (! $newHolder instanceof PassHolder) {
            return $newHolder;
        }

        return $newHolder->replicate(['pass_id']);
    }

    /** @param Collection<int, Pass> $passes */
    private function assertSingleOwner(Collection $passes): void
    {
        $ownerTuples = $passes
            ->map(fn (Pass $pass): string => ($pass->owner_type ?? '') . '|' . ($pass->owner_id ?? ''))
            ->unique();

        if ($ownerTuples->count() > 1) {
            throw new AuthorizationException('Bulk transfer requires passes of a single owner.');
        }
    }
}
