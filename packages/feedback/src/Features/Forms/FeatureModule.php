<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms;

use AIArmada\Feedback\Features\Forms\Actions\CreateFeedbackFormFromTemplateAction;
use AIArmada\Feedback\Features\Forms\Api\CreatesFormsFromTemplates;
use AIArmada\Feedback\Features\Forms\Api\ReadsFormMetrics;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Forms\Api\ReadsSubjectForms;
use AIArmada\Feedback\Features\Forms\Api\ReadsSubmissionForm;
use AIArmada\Feedback\Features\Forms\Queries\ReadFormMetricsQuery;
use AIArmada\Feedback\Features\Forms\Queries\ReadOwnedFormIdentityQuery;
use AIArmada\Feedback\Features\Forms\Queries\ReadSubjectFormsQuery;
use AIArmada\Feedback\Features\Forms\Queries\ReadSubmissionFormQuery;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ReadsSubjectForms::class, ReadSubjectFormsQuery::class);
        $app->bind(ReadsFormMetrics::class, ReadFormMetricsQuery::class);
        $app->bind(ReadsOwnedFormIdentity::class, ReadOwnedFormIdentityQuery::class);
        $app->bind(ReadsSubmissionForm::class, ReadSubmissionFormQuery::class);
        $app->bind(CreatesFormsFromTemplates::class, CreateFeedbackFormFromTemplateAction::class);
    }

    public function boot(Application $app): void {}
}
