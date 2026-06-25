<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Feedback;

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Feedback\FeedbackServiceProvider;

abstract class FeedbackTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            FeedbackServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('feedback.database.json_column_type', 'json');
        $app['config']->set('feedback.owner.enabled', true);
        $app['config']->set('feedback.owner.include_global', false);
        $app['config']->set('feedback.owner.auto_assign_on_create', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/feedback/database/migrations');
    }
}
