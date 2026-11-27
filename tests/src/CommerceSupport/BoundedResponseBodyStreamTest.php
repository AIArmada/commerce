<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Drivers\Http\BoundedResponseBodyStream;
use AIArmada\CommerceSupport\Drivers\Http\ResponseBodyLimitExceeded;

test('bounded response stream aborts writes beyond the byte limit', function (): void {
    $stream = new BoundedResponseBodyStream(5);

    expect($stream->write('hello'))->toBe(5)
        ->and(fn () => $stream->write('!'))->toThrow(ResponseBodyLimitExceeded::class);
});
