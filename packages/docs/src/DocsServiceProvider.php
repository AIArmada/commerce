<?php

declare(strict_types=1);

namespace AIArmada\Docs;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Contracts\DocumentSequence;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\Drivers\SpatiePdfRenderer;
use AIArmada\Docs\Platform\ExtensionRegistry;
use AIArmada\Docs\Platform\FeatureCatalog;
use AIArmada\Docs\Platform\FeatureModule;
use AIArmada\Docs\Rendering\TiptapJsonRenderer;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Services\DocService;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DocsServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('docs')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('docs')
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException('Invalid docs feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }

        $this->app->scoped(Numbering\DocumentNumberRegistry::class);

        $this->app->singleton(RichContentRendererInterface::class, TiptapJsonRenderer::class);
        $this->app->singleton(PdfRenderer::class, SpatiePdfRenderer::class);
        $this->app->singleton(DocRenderService::class);

        // Register Doc Service
        $this->app->scoped(DocService::class, function ($app) {
            return new DocService(
                $app->make(Numbering\DocumentNumberRegistry::class),
                $app->make(DocumentSequence::class),
                $app->make(Services\DocTotals::class),
            );
        });

        $this->app->alias(DocService::class, 'doc');
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            DocService::class,
            DocServiceInterface::class,
            DocRenderService::class,
            RichContentRendererInterface::class,
            PdfRenderer::class,
            DocumentSequence::class,
            Services\DocTotals::class,
            DocumentPaymentRecorder::class,
            'doc',
            Numbering\DocumentNumberRegistry::class,
        ];
    }
}
