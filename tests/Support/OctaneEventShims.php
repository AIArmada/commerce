<?php

declare(strict_types=1);

if (! class_exists('Laravel\\Octane\\Events\\RequestReceived')) {
    eval(<<<'PHP'
namespace Laravel\Octane\Events;

final class RequestReceived {}

final class RequestTerminated {}
PHP);
}
