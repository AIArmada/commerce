<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventOccurrence;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateOccurrenceCartLineAction
{
    use AsAction;

    public function handle(EventOccurrence $occurrence, array $data = []): mixed
    {
        return null;
    }
}
