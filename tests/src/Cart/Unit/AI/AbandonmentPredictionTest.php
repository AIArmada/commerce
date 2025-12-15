<?php

declare(strict_types=1);

use AIArmada\Cart\AI\AbandonmentPrediction;
use AIArmada\Cart\AI\Intervention;

describe('AbandonmentPrediction', function (): void {
    it('can be instantiated with all parameters', function (): void {
        $predictedAt = new DateTimeImmutable();
        $intervention = new Intervention('email', 1, 'Send recovery email', ['delay_minutes' => 30]);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.75,
            riskLevel: 'high',
            features: ['cart_age' => 0.6, 'item_count' => 0.3],
            interventions: [$intervention],
            predictedAt: $predictedAt
        );

        expect($prediction->cartId)->toBe('cart-123')
            ->and($prediction->probability)->toBe(0.75)
            ->and($prediction->riskLevel)->toBe('high')
            ->and($prediction->features)->toBe(['cart_age' => 0.6, 'item_count' => 0.3])
            ->and($prediction->interventions)->toHaveCount(1)
            ->and($prediction->predictedAt)->toBe($predictedAt);
    });

    it('detects high risk correctly', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.9,
            riskLevel: 'high',
            features: [],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->isHighRisk())->toBeTrue();
    });

    it('detects non-high risk correctly', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.5,
            riskLevel: 'medium',
            features: [],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->isHighRisk())->toBeFalse();
    });

    it('detects when intervention is needed', function (): void {
        $intervention = new Intervention('email', 1, 'Send recovery email');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.75,
            riskLevel: 'high',
            features: [],
            interventions: [$intervention],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->needsIntervention())->toBeTrue();
    });

    it('detects when no intervention is needed', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.2,
            riskLevel: 'low',
            features: [],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->needsIntervention())->toBeFalse();
    });

    it('gets top intervention when available', function (): void {
        $intervention1 = new Intervention('email', 1, 'Send recovery email');
        $intervention2 = new Intervention('discount', 2, 'Offer discount');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.8,
            riskLevel: 'high',
            features: [],
            interventions: [$intervention1, $intervention2],
            predictedAt: new DateTimeImmutable()
        );

        $topIntervention = $prediction->getTopIntervention();
        expect($topIntervention)->toBe($intervention1);
    });

    it('returns null when no interventions available', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.2,
            riskLevel: 'low',
            features: [],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->getTopIntervention())->toBeNull();
    });

    it('filters interventions by type', function (): void {
        $intervention1 = new Intervention('email', 1, 'Send recovery email');
        $intervention2 = new Intervention('discount', 2, 'Offer discount');
        $intervention3 = new Intervention('email', 3, 'Send reminder email');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.8,
            riskLevel: 'high',
            features: [],
            interventions: [$intervention1, $intervention2, $intervention3],
            predictedAt: new DateTimeImmutable()
        );

        $emailInterventions = $prediction->getInterventionsByType('email');
        expect($emailInterventions)->toHaveCount(2);
    });

    it('returns empty array when filtering by non-existent type', function (): void {
        $intervention = new Intervention('email', 1, 'Send recovery email');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.8,
            riskLevel: 'high',
            features: [],
            interventions: [$intervention],
            predictedAt: new DateTimeImmutable()
        );

        $smsInterventions = $prediction->getInterventionsByType('sms');
        expect($smsInterventions)->toBeEmpty();
    });

    it('gets most significant feature', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.75,
            riskLevel: 'high',
            features: ['cart_age' => 0.6, 'item_count' => 0.3, 'cart_value' => 0.8],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        $mostSignificant = $prediction->getMostSignificantFeature();
        expect($mostSignificant)->toBe(['feature' => 'cart_value', 'value' => 0.8]);
    });

    it('returns null for most significant feature when features empty', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.5,
            riskLevel: 'medium',
            features: [],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->getMostSignificantFeature())->toBeNull();
    });

    it('returns null for most significant feature when all features are zero', function (): void {
        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.5,
            riskLevel: 'medium',
            features: ['cart_age' => 0.0, 'item_count' => 0.0],
            interventions: [],
            predictedAt: new DateTimeImmutable()
        );

        expect($prediction->getMostSignificantFeature())->toBeNull();
    });

    it('converts to array correctly', function (): void {
        $predictedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $intervention = new Intervention('email', 1, 'Send recovery email');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.75,
            riskLevel: 'high',
            features: ['cart_age' => 0.6],
            interventions: [$intervention],
            predictedAt: $predictedAt
        );

        $array = $prediction->toArray();

        expect($array)->toBeArray()
            ->and($array['cart_id'])->toBe('cart-123')
            ->and($array['probability'])->toBe(0.75)
            ->and($array['probability_percentage'])->toBe(75.0)
            ->and($array['risk_level'])->toBe('high')
            ->and($array['features'])->toBe(['cart_age' => 0.6])
            ->and($array['interventions'])->toHaveCount(1)
            ->and($array['predicted_at'])->toBe('2024-01-15 10:30:00');
    });
});
