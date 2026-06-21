<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\Communication;

final class DeleteCommunicationAggregateAction
{
    public function handle(string $communicationId): void
    {
        $communication = Communication::query()->findOrFail($communicationId);

        $communication->delete();
    }
}
