<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentFeedback;

use AIArmada\Commerce\Tests\Feedback\FeedbackTestCase;
use AIArmada\FilamentFeedback\FilamentFeedbackServiceProvider;

abstract class FilamentFeedbackTestCase extends FeedbackTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FilamentFeedbackServiceProvider::class,
        ];
    }
}
