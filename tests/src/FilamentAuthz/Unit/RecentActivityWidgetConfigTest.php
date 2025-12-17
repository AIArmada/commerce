<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use ReflectionProperty;

describe('RecentActivityWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new RecentActivityWidget;

        expect($widget)->toBeInstanceOf(RecentActivityWidget::class);
    });

    it('has correct sort order', function (): void {
        $reflection = new ReflectionProperty(RecentActivityWidget::class, 'sort');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toBe(3);
    });

    it('spans full column', function (): void {
        $widget = new RecentActivityWidget;

        $reflection = new ReflectionProperty(RecentActivityWidget::class, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });

    it('has correct heading', function (): void {
        $reflection = new ReflectionProperty(RecentActivityWidget::class, 'heading');
        $reflection->setAccessible(true);

        expect($reflection->getValue())->toBe('Recent Permission Activity');
    });
});
