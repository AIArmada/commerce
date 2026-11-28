<?php

declare(strict_types=1);

use AIArmada\Jnt\Drivers\JntClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('redacts nested government id, mailbox and street fields in request logs', function (): void {
    Http::fake([
        '*/api/order/addOrder' => Http::response(['code' => '1', 'msg' => 'ok', 'data' => []], 200),
    ]);

    Log::shouldReceive('channel')->twice()->with('testing')->andReturnSelf();

    $loggedContext = [];

    Log::shouldReceive('log')
        ->twice()
        ->withArgs(function (string $level, string $message, array $context) use (&$loggedContext): bool {
            if ($message === 'J&T API Request') {
                $loggedContext = $context;
            }

            return true;
        });

    $client = new JntClient('https://example.test', 'account', 'secret', [
        'logging' => ['enabled' => true, 'channel' => 'testing', 'level' => 'info'],
    ]);

    $client->post('/api/order/addOrder', [
        'receiver' => [
            'idCard' => '900101-01-1234',
            'mailBox' => 'MB-12',
            'street' => 'Jalan Rahsia 1',
            'address' => '1 Jalan Rahsia',
        ],
    ]);

    expect($loggedContext['data']['receiver']['idCard'])->toBe('***MASKED***')
        ->and($loggedContext['data']['receiver']['mailBox'])->toBe('***MASKED***')
        ->and($loggedContext['data']['receiver']['street'])->toBe('***MASKED***')
        ->and($loggedContext['data']['receiver']['address'])->toBe('***MASKED***');
});
