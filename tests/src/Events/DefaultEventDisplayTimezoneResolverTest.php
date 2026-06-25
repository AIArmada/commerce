<?php

declare(strict_types=1);

use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;

it('prefers the event timezone and falls back to the configured default', function (): void {
    config()->set('events.defaults.timezone', 'Asia/Kuala_Lumpur');

    $resolver = new DefaultEventDisplayTimezoneResolver;

    expect($resolver->resolve('Europe/London'))->toBe('Europe/London')
        ->and($resolver->resolve())->toBe('Asia/Kuala_Lumpur');
});
