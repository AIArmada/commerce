<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Reporting;

use AIArmada\Signals\Contracts\ReportInterface;
use AIArmada\Signals\Features\Reporting\Actions\AggregateDailyMetrics;
use AIArmada\Signals\Features\Reporting\Queries\ReportRegistry;
use AIArmada\Signals\Features\Reporting\Queries\AcquisitionReportService;
use AIArmada\Signals\Features\Reporting\Queries\ContentPerformanceReportService;
use AIArmada\Signals\Features\Reporting\Queries\ConversionFunnelReportService;
use AIArmada\Signals\Features\Reporting\Queries\DevicesReportService;
use AIArmada\Signals\Features\Reporting\Queries\GoalsReportService;
use AIArmada\Signals\Features\Reporting\Queries\JourneyReportService;
use AIArmada\Signals\Features\Reporting\Queries\LiveActivityReportService;
use AIArmada\Signals\Features\Reporting\Queries\PageViewReportService;
use AIArmada\Signals\Features\Reporting\Queries\RetentionReportService;
use AIArmada\Signals\Features\Reporting\Queries\SignalsDashboardService;
use AIArmada\Signals\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(SignalsDashboardService::class);
        $app->singleton(AggregateDailyMetrics::class);
        $app->singleton(ReportRegistry::class, function ($app): ReportRegistry {
            $registry = new ReportRegistry;

            $registry->register(new class($app->make(AcquisitionReportService::class)) implements ReportInterface
            {
                public function __construct(private AcquisitionReportService $service) {}

                public function type(): string
                {
                    return 'acquisition';
                }

                public function name(): string
                {
                    return 'Acquisition';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(ConversionFunnelReportService::class)) implements ReportInterface
            {
                public function __construct(private ConversionFunnelReportService $service) {}

                public function type(): string
                {
                    return 'conversion_funnel';
                }

                public function name(): string
                {
                    return 'Conversion Funnel';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(RetentionReportService::class)) implements ReportInterface
            {
                public function __construct(private RetentionReportService $service) {}

                public function type(): string
                {
                    return 'retention';
                }

                public function name(): string
                {
                    return 'Retention';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(JourneyReportService::class)) implements ReportInterface
            {
                public function __construct(private JourneyReportService $service) {}

                public function type(): string
                {
                    return 'journey';
                }

                public function name(): string
                {
                    return 'Journey';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(PageViewReportService::class)) implements ReportInterface
            {
                public function __construct(private PageViewReportService $service) {}

                public function type(): string
                {
                    return 'page_view';
                }

                public function name(): string
                {
                    return 'Page Views';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(ContentPerformanceReportService::class)) implements ReportInterface
            {
                public function __construct(private ContentPerformanceReportService $service) {}

                public function type(): string
                {
                    return 'content_performance';
                }

                public function name(): string
                {
                    return 'Content Performance';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(DevicesReportService::class)) implements ReportInterface
            {
                public function __construct(private DevicesReportService $service) {}

                public function type(): string
                {
                    return 'devices';
                }

                public function name(): string
                {
                    return 'Devices';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(GoalsReportService::class)) implements ReportInterface
            {
                public function __construct(private GoalsReportService $service) {}

                public function type(): string
                {
                    return 'goals';
                }

                public function name(): string
                {
                    return 'Goals';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            $registry->register(new class($app->make(LiveActivityReportService::class)) implements ReportInterface
            {
                public function __construct(private LiveActivityReportService $service) {}

                public function type(): string
                {
                    return 'live_activity';
                }

                public function name(): string
                {
                    return 'Live Activity';
                }

                public function summary(?string $trackedPropertyId = null, ?string $from = null, ?string $until = null): array
                {
                    return $this->service->summary($trackedPropertyId, $from, $until);
                }
            });

            return $registry;
        });
    }

    public function boot(Application $app): void {}
}
