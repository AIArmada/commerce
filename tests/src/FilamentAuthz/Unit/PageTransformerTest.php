<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->transformer = new PageTransformer;
});

describe('PageTransformer', function (): void {
    it('throws exception for non-existent class', function (): void {
        $this->transformer->transform('NonExistentClass');
    })->throws(\InvalidArgumentException::class, 'Invalid page class');

    it('throws exception for class that is not a Page subclass', function (): void {
        $this->transformer->transform(stdClass::class);
    })->throws(\InvalidArgumentException::class, 'Invalid page class');

    it('transforms a valid page class', function (): void {
        // Dashboard is a valid page class that exists in Filament
        $result = $this->transformer->transform(Dashboard::class, 'admin');

        expect($result)->toBeInstanceOf(DiscoveredPage::class)
            ->and($result->fqcn)->toBe(Dashboard::class)
            ->and($result->panel)->toBe('admin')
            ->and($result->permissions)->toContain('viewDashboard');
    });

    it('extracts metadata with correct keys', function (): void {
        $result = $this->transformer->transform(Dashboard::class);

        expect($result->metadata)->toBeArray()
            ->and($result->metadata)->toHaveKeys(['hasForm', 'hasTable', 'isWizard', 'isDashboard', 'hasWidgets']);
    });

    it('returns DiscoveredPage without panel when not provided', function (): void {
        $result = $this->transformer->transform(Dashboard::class);

        expect($result->panel)->toBeNull();
    });

    it('generates correct permission name from class basename', function (): void {
        $result = $this->transformer->transform(Dashboard::class);

        expect($result->permissions)->toContain('viewDashboard');
    });
});
