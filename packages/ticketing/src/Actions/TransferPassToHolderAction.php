<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Support\HolderAttributesValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class TransferPassToHolderAction
{
    use AsAction;

    public function handle(
        Pass $pass,
        PassHolder | Model | null $newHolder,
        array $holderAttributes = [],
        ?string $reason = null,
        ?Model $authorizedBy = null,
    ): PassHolder {
        if ($authorizedBy !== null) {
            Gate::forUser($authorizedBy)->authorize('transfer', $pass);
        }

        return DB::transaction(function () use ($pass, $newHolder, $holderAttributes, $reason) {
            $resolvedHolder = $this->resolveHolder($pass, $newHolder, $holderAttributes);

            return app(PassTransferServiceInterface::class)->transfer($pass, $resolvedHolder, $reason);
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
            $holder->is_current = true;
            $holder->save();

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

        $holder->is_current = true;
        $holder->save();

        return $holder;
    }
}
