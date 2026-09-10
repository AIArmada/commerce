<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\DB;

final class DeleteCommunicationAggregateAction
{
    public function handle(string $communicationId): void
    {
        DB::transaction(function () use ($communicationId): void {
            /** @var Communication $communication */
            $communication = OwnerWriteGuard::findOrFailForOwner(
                Communication::class,
                $communicationId,
            );

            $communication->delete();
        });
    }
}
