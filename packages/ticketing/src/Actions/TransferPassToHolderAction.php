<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class TransferPassToHolderAction
{
    use AsAction;

    public function handle(
        Pass $pass,
        PassHolder | Model | null $newHolder,
        array $holderAttributes = [],
        ?string $reason = null,
    ): PassHolder {
        $resolvedHolder = $this->resolveHolder($pass, $newHolder, $holderAttributes);

        return app(PassTransferServiceInterface::class)->transfer($pass, $resolvedHolder, $reason);
    }

    /** @param array<string, mixed> $holderAttributes */
    private function resolveHolder(
        Pass $pass,
        PassHolder | Model | null $newHolder,
        array $holderAttributes = [],
    ): PassHolder {
        if ($newHolder instanceof PassHolder) {
            return $newHolder;
        }

        if ($newHolder instanceof Model) {
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
