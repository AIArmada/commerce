<?php

declare(strict_types=1);

if (! class_exists('Laravel\\Octane\\Events\\RequestReceived')) {
    eval(<<<'PHP'
namespace Laravel\Octane\Events;

final class RequestReceived {}

final class RequestTerminated {}

final class TaskReceived {}

final class TaskTerminated {}

final class TickReceived {}

final class TickTerminated {}
PHP);
}
