<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Events\PassesBulkTransferred;
use AIArmada\Ticketing\Exceptions\BulkTransferSizeExceededException;
use AIArmada\Ticketing\Jobs\BulkSendTransferNotificationsJob;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
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

        return DB::transaction(function () use ($passes, $newHolder, $reason, $holderAttributes) {
            $newHolders = new Collection;

            foreach ($passes as $pass) {
                $action = resolve(TransferPassToHolderAction::class);
                $newHolders->push(
                    $action->handle($pass, $newHolder, $holderAttributes, $reason)
                );
            }

            $event = new PassesBulkTransferred($passes, $newHolders->first(), $reason);
            dispatch(new BulkSendTransferNotificationsJob($event));

            return $newHolders;
        });
    }
}
