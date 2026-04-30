<?php

declare(strict_types=1);

use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\EditCustomer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\ListCustomers;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages\EditSegment;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages\ListSegments;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages\ViewSegment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

it('ListCustomers defines a Create header action', function (): void {
    $page = new ListCustomers;

    $actions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array<int, object> $result */
        $result = $method->invoke($page);

        return $result;
    })();

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(CreateAction::class);
});

it('EditCustomer defines View and Delete header actions', function (): void {
    $page = new EditCustomer;

    $actions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array<int, object> $result */
        $result = $method->invoke($page);

        return $result;
    })();

    expect($actions)->toHaveCount(2);
    expect($actions[0])->toBeInstanceOf(ViewAction::class);
    expect($actions[1])->toBeInstanceOf(DeleteAction::class);
});

it('ListSegments defines a Create header action', function (): void {
    $page = new ListSegments;

    $actions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array<int, object> $result */
        $result = $method->invoke($page);

        return $result;
    })();

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(CreateAction::class);
});

it('EditSegment defines View and Delete header actions', function (): void {
    $page = new EditSegment;

    $actions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array<int, object> $result */
        $result = $method->invoke($page);

        return $result;
    })();

    expect($actions)->toHaveCount(2);
    expect($actions[0])->toBeInstanceOf(ViewAction::class);
    expect($actions[1])->toBeInstanceOf(DeleteAction::class);
});

it('ViewSegment defines an Edit header action', function (): void {
    $page = new ViewSegment;

    $actions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');

        /** @var array<int, object> $result */
        $result = $method->invoke($page);

        return $result;
    })();

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(EditAction::class);
});
