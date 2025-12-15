<?php

declare(strict_types=1);

use AIArmada\Cart\Commands\AddItemCommand;
use AIArmada\Cart\Commands\ApplyConditionCommand;
use AIArmada\Cart\Commands\CartCommandBus;
use AIArmada\Cart\Commands\ClearCartCommand;
use AIArmada\Cart\Commands\RemoveItemCommand;
use AIArmada\Cart\Commands\UpdateItemQuantityCommand;
use Illuminate\Contracts\Container\Container;

describe('CartCommandBus', function (): void {
    it('can be instantiated', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus)->toBeInstanceOf(CartCommandBus::class);
    });

    it('has handler for AddItemCommand', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler(AddItemCommand::class))->toBeTrue();
    });

    it('has handler for UpdateItemQuantityCommand', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler(UpdateItemQuantityCommand::class))->toBeTrue();
    });

    it('has handler for RemoveItemCommand', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler(RemoveItemCommand::class))->toBeTrue();
    });

    it('has handler for ApplyConditionCommand', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler(ApplyConditionCommand::class))->toBeTrue();
    });

    it('has handler for ClearCartCommand', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler(ClearCartCommand::class))->toBeTrue();
    });

    it('does not have handler for unknown command', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        expect($bus->hasHandler('App\\Commands\\UnknownCommand'))->toBeFalse();
    });

    it('returns all registered commands', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        $commands = $bus->getRegisteredCommands();

        expect($commands)->toBeArray()
            ->and($commands)->toContain(AddItemCommand::class)
            ->and($commands)->toContain(UpdateItemQuantityCommand::class)
            ->and($commands)->toContain(RemoveItemCommand::class)
            ->and($commands)->toContain(ApplyConditionCommand::class)
            ->and($commands)->toContain(ClearCartCommand::class)
            ->and($commands)->toHaveCount(5);
    });

    it('throws exception when dispatching unknown command', function (): void {
        $container = Mockery::mock(Container::class);
        $bus = new CartCommandBus($container);

        $unknownCommand = new class {
            public string $data = 'test';
        };

        $bus->dispatch($unknownCommand);
    })->throws(InvalidArgumentException::class, 'No handler registered for command');
});
