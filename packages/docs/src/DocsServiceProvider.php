<?php

declare(strict_types=1);

namespace AIArmada\Docs;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\Rendering\TiptapJsonRenderer;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\SequenceManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class DocsServiceProvider extends PackageServiceProvider
{
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
        $this->app->scoped(Numbering\DocumentNumberRegistry::class);

        // Sequence manager
        $this->app->singleton(SequenceManager::class);

        $this->app->singleton(RichContentRendererInterface::class, TiptapJsonRenderer::class);
        $this->app->singleton(DocRenderService::class);

        // Register Doc Service
        $this->app->scoped(DocService::class, function ($app) {
            return new DocService(
                $app->make(Numbering\DocumentNumberRegistry::class),
                $app->make(SequenceManager::class),
                $app->make(Services\DocTotals::class),
                $app->make(Services\DocPaymentRecorder::class),
            );
        });

        // Bind interface to implementation
        $this->app->alias(DocService::class, DocServiceInterface::class);
        $this->app->alias(DocService::class, 'doc');
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
            SequenceManager::class,
            Services\DocTotals::class,
            Services\DocPaymentRecorder::class,
            'doc',
            Numbering\DocumentNumberRegistry::class,
        ];
    }
}
