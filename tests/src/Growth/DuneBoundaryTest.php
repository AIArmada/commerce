<?php

declare(strict_types=1);

use AIArmada\Growth\EntryPoints\Commands\ArchiveExperimentsCommand;
use AIArmada\Growth\EntryPoints\Commands\RecomputeExperimentAssignmentsCommand;
use AIArmada\Growth\EntryPoints\Http\DefaultRequestExperimentSubjectResolver;
use AIArmada\Growth\EntryPoints\Http\Middleware\ResolveExperiment;
use AIArmada\Growth\EntryPoints\Http\RequestExperimentSubjectResolver;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Growth\Support\Context\ExperimentContextManager;
use AIArmada\Signals\Features\Browser\Api\BrowserPageProperties;
use AIArmada\Signals\Features\Browser\Queries\BrowserPagePropertyRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

arch('growth source uses strict types')
    ->expect('AIArmada\\Growth')
    ->toUseStrictTypes();

arch('growth source never imports host application code')
    ->expect('AIArmada\\Growth')
    ->not->toUse('App');

arch('growth public data does not depend on runtime models or actions')
    ->expect('AIArmada\\Growth\\Data')
    ->not->toUse([
        'AIArmada\\Growth\\Actions',
        'AIArmada\\Growth\\Models',
        'Illuminate\\Database\\Eloquent',
        'Illuminate\\Http',
    ]);

it('boots the Growth entry points and tables through the shared Testbench app', function (): void {
    expect(app(RequestExperimentSubjectResolver::class))->toBeInstanceOf(DefaultRequestExperimentSubjectResolver::class)
        ->and(app(BrowserPageProperties::class))->toBeInstanceOf(BrowserPagePropertyRegistry::class)
        ->and(app(BrowserPageProperties::class)->currentPageProperties())->toBe(app(ExperimentContextManager::class)->currentPageProperties())
        ->and(app('router')->getMiddleware()['growth.experiment'])->toBe(ResolveExperiment::class)
        ->and(Artisan::all()['growth:archive-experiments'])->toBeInstanceOf(ArchiveExperimentsCommand::class)
        ->and(Artisan::all()['growth:recompute-assignments'])->toBeInstanceOf(RecomputeExperimentAssignmentsCommand::class)
        ->and(Schema::hasTable((new Experiment)->getTable()))->toBeTrue()
        ->and(Schema::hasTable((new Variant)->getTable()))->toBeTrue()
        ->and(Schema::hasTable((new Assignment)->getTable()))->toBeTrue();
});
