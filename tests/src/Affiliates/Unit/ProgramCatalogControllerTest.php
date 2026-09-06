<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Http\Controllers\ProgramCatalogController;
use AIArmada\Affiliates\Models\AffiliateProgram;

beforeEach(function (): void {
    $this->controller = app(ProgramCatalogController::class);

    $this->program = AffiliateProgram::create([
        'name' => 'Public Catalog Program',
        'slug' => 'public-catalog-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
        'default_commission_rate_basis_points' => 1000,
    ]);
});

describe('ProgramCatalogController', function (): void {
    test('index lists available programs', function (): void {
        $response = $this->controller->index();

        expect($response->getStatusCode())->toBe(200);

        $ids = collect(json_decode($response->getContent(), true)['data'])->pluck('program_id');
        expect($ids)->toContain((string) $this->program->getKey());
    });

    test('show returns snapshot with resolved base rate', function (): void {
        $response = $this->controller->show((string) $this->program->getKey());

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['program_id'])->toBe((string) $this->program->getKey());
        expect($data['base']['default_rate_bp'])->toBe(1000);
        expect($data['subjects'])->toBeArray();
        expect($data['variable_extras'])->toHaveKeys(['volume_tiers', 'promotions']);
    });

    test('show returns 404 for non-public programs', function (): void {
        $draft = AffiliateProgram::create([
            'name' => 'Draft Program',
            'slug' => 'draft-catalog-' . uniqid(),
            'status' => ProgramStatus::Draft,
            'visibility' => ProgramVisibility::Public,
        ]);

        expect($this->controller->show((string) $draft->getKey())->getStatusCode())->toBe(404);
        expect($this->controller->show('no-such-id')->getStatusCode())->toBe(404);
    });

    test('returns 404 when catalog is disabled', function (): void {
        config()->set('affiliates.catalog.enabled', false);

        expect($this->controller->index()->getStatusCode())->toBe(404);
        expect($this->controller->show((string) $this->program->getKey())->getStatusCode())->toBe(404);
    });
});
