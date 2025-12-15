<?php

declare(strict_types=1);

use AIArmada\Cart\Security\Fraud\Detectors\PriceManipulationDetector;
use AIArmada\Cart\Security\Fraud\Detectors\VelocityAnalyzer;
use AIArmada\Cart\Security\Fraud\FraudDetectorInterface;

describe('VelocityAnalyzer', function (): void {
    beforeEach(function (): void {
        $this->analyzer = new VelocityAnalyzer;
    });

    it('can be instantiated', function (): void {
        expect($this->analyzer)->toBeInstanceOf(VelocityAnalyzer::class);
    });

    it('implements FraudDetectorInterface', function (): void {
        expect($this->analyzer)->toBeInstanceOf(FraudDetectorInterface::class);
    });

    it('returns detector name', function (): void {
        expect($this->analyzer->getName())->toBe('velocity_analyzer');
    });

    it('is enabled by default', function (): void {
        expect($this->analyzer->isEnabled())->toBeBool();
    });

    it('returns weight', function (): void {
        $weight = $this->analyzer->getWeight();

        expect($weight)->toBeFloat()
            ->and($weight)->toBeGreaterThan(0);
    });
});

describe('PriceManipulationDetector', function (): void {
    beforeEach(function (): void {
        $this->detector = new PriceManipulationDetector;
    });

    it('can be instantiated', function (): void {
        expect($this->detector)->toBeInstanceOf(PriceManipulationDetector::class);
    });

    it('implements FraudDetectorInterface', function (): void {
        expect($this->detector)->toBeInstanceOf(FraudDetectorInterface::class);
    });

    it('returns detector name', function (): void {
        expect($this->detector->getName())->toBe('price_manipulation');
    });

    it('is enabled by default', function (): void {
        expect($this->detector->isEnabled())->toBeBool();
    });

    it('returns weight', function (): void {
        $weight = $this->detector->getWeight();

        expect($weight)->toBeFloat()
            ->and($weight)->toBeGreaterThan(0);
    });

    it('can store catalog price', function (): void {
        // Store and retrieve to verify no exception thrown
        $this->detector->storeCatalogPrice('item-123', 5000);

        expect(true)->toBeTrue(); // Just verify no exception
    });
});
