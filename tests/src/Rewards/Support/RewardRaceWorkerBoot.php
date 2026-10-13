<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Tests\Support;

use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\Rewards\RewardsServiceProvider;
use Illuminate\Foundation\Application;
use Lorisleiva\Actions\ActionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Lean harness boot for reward race workers in independent OS processes.
 *
 * Boots only commerce-support + rewards against the isolated fixture
 * servers; the worker scripts point the default connection at their
 * per-test database right after boot. Owner enforcement stays off,
 * matching the single-tenant host these races were authored against:
 * workers act on parent-seeded programs whose owner tuple belongs to
 * the parent process, and only the program row carries owner columns.
 * Never referenced by Pest test files directly.
 */
final class RewardRaceWorkerBoot extends Orchestra
{
    public function bootForWorker(): void
    {
        $this->setUp();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionServiceProvider::class,
            LaravelDataServiceProvider::class,
            SupportServiceProvider::class,
            RewardsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('activitylog.enabled', false);
        $app['config']->set('data.date_format', DATE_ATOM);

        $app['config']->set('rewards.owner.enabled', false);
        $app['config']->set('rewards.owner.include_global', false);
        $app['config']->set('rewards.owner.auto_assign_on_create', false);
        $app['config']->set('rewards.outcomes', ['contribution', 'attendance', 'referral', 'registration', 'person_follow', 'org_follow', 'series_follow', 'reference_follow', 'saved_search', 'saved_item', 'rsvp']);
        $app['config']->set('rewards.moderated_outcomes', ['contribution']);
        $app['config']->set('rewards.recipient_types', ['user']);
        $app['config']->set('rewards.payouts.methods', ['manual']);
    }
}
