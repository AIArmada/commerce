<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Response;

final class ResponseChanged
{
    public function __construct(public Response $response, public string $previousType) {}
}
