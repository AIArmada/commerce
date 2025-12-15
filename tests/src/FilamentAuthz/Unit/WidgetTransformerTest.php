<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->transformer = new WidgetTransformer;
});

describe('WidgetTransformer', function (): void {
    it('throws exception for non-existent class', function (): void {
        $this->transformer->transform('NonExistentWidgetClass');
    })->throws(InvalidArgumentException::class, 'Invalid widget class');

    it('throws exception for class that is not a Widget subclass', function (): void {
        $this->transformer->transform(stdClass::class);
    })->throws(InvalidArgumentException::class, 'Invalid widget class');

    it('transforms a valid widget class', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class, 'admin');

        expect($result)->toBeInstanceOf(DiscoveredWidget::class)
            ->and($result->fqcn)->toBe(PermissionStatsWidget::class)
            ->and($result->panel)->toBe('admin')
            ->and($result->name)->toBe('permission_stats_widget');
    });

    it('detects stats widget type', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->type)->toBe('stats');
    });

    it('returns DiscoveredWidget without panel when not provided', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->panel)->toBeNull();
    });

    it('generates snake_case name from class basename', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->name)->toBe('permission_stats_widget');
    });

    it('extracts metadata with correct keys', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->metadata)->toBeArray()
            ->and($result->metadata)->toHaveKeys(['isChart', 'isStats', 'isLivewire', 'isPolling', 'chartType']);
    });

    it('identifies stats widget in metadata', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->metadata['isStats'])->toBeTrue()
            ->and($result->metadata['isChart'])->toBeFalse()
            ->and($result->metadata['isLivewire'])->toBeTrue();
    });

    it('generates correct permission name', function (): void {
        $result = $this->transformer->transform(PermissionStatsWidget::class);

        expect($result->permissions)->toContain('viewPermissionStatsWidget');
    });
});
