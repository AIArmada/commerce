<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Features\Browser\Api\BrowserPageProperties;
use AIArmada\Signals\Features\Browser\Api\BrowserPagePropertyContributor;
use AIArmada\Signals\Features\Browser\Queries\BrowserPagePropertyRegistry;

uses(SignalsTestCase::class);

it('merges browser page properties from every registered contributor', function (): void {
    $registry = app(BrowserPageProperties::class);

    expect($registry)->toBeInstanceOf(BrowserPagePropertyRegistry::class);

    $registry->add(new class implements BrowserPagePropertyContributor
    {
        public function currentPageProperties(): array
        {
            return ['page_type' => 'landing', 'shared' => 'first'];
        }
    });

    $registry->add(new class implements BrowserPagePropertyContributor
    {
        public function currentPageProperties(): array
        {
            return ['experiment_id' => 'experiment-1', 'shared' => 'second'];
        }
    });

    expect($registry->currentPageProperties())->toBe([
        'page_type' => 'landing',
        'shared' => 'second',
        'experiment_id' => 'experiment-1',
    ]);
});

it('replaces a contributor of the same class instead of duplicating it', function (): void {
    $registry = app(BrowserPageProperties::class);
    $contributor = new class implements BrowserPagePropertyContributor
    {
        public function currentPageProperties(): array
        {
            return ['value' => 1];
        }
    };

    $registry->add($contributor);
    $registry->add($contributor);

    expect($registry->currentPageProperties())->toBe(['value' => 1]);
});
