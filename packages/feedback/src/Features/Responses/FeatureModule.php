<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses;

use AIArmada\Feedback\Features\Responses\Actions\DeleteResponseDependenciesAction;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Features\Responses\Api\ReadsResponseMetrics;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectFeedbackMetrics;
use AIArmada\Feedback\Features\Responses\Api\ReadsSubjectResponses;
use AIArmada\Feedback\Features\Responses\Api\ReadsTestimonialSource;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Responses\Policies\FeedbackResponsePolicy;
use AIArmada\Feedback\Features\Responses\Queries\ReadResponseMetricsQuery;
use AIArmada\Feedback\Features\Responses\Queries\ReadSubjectFeedbackMetricsQuery;
use AIArmada\Feedback\Features\Responses\Queries\ReadSubjectResponsesQuery;
use AIArmada\Feedback\Features\Responses\Queries\ReadTestimonialSourceQuery;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReadsSubjectResponses::class, ReadSubjectResponsesQuery::class);
        $app->bind(ReadsResponseMetrics::class, ReadResponseMetricsQuery::class);
        $app->bind(ReadsSubjectFeedbackMetrics::class, ReadSubjectFeedbackMetricsQuery::class);
        $app->bind(ReadsTestimonialSource::class, ReadTestimonialSourceQuery::class);
        $app->bind(DeletesResponseDependencies::class, DeleteResponseDependenciesAction::class);
        Gate::policy(FeedbackResponse::class, FeedbackResponsePolicy::class);
    }

    public function boot(Application $app): void {}
}
