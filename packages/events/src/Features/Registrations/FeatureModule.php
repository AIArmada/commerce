<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations;

use AIArmada\Events\Contracts\EventRefundEligibility;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationQuestionResolver;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Events\EventRegistrationCancelled;
use AIArmada\Events\Features\Registrations\Actions\DefaultRegistrationService;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\EntryPoints\Listeners\CancelBundleChildrenOnParentCanceled;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationQuestion;
use AIArmada\Events\Features\Registrations\Policies\EventRegistrationPolicy;
use AIArmada\Events\Features\Registrations\Queries\DefaultEventRefundEligibility;
use AIArmada\Events\Features\Registrations\Queries\DefaultEventRegistrationEligibility;
use AIArmada\Events\Features\Registrations\Queries\DefaultEventRegistrationQuestionResolver;
use AIArmada\Events\Features\Registrations\Queries\DefaultEventRegistrationScopeResolver;
use AIArmada\Events\Features\Registrations\Queries\EloquentRegistrationsApi;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('registration', EventRegistration::class);
        $this->defaultModelConfig('registration_question', EventRegistrationQuestion::class);
        $this->defaultModelConfig('registration_participant', EventRegistrationParticipant::class);

        $app->bind(RegistrationServiceInterface::class, DefaultRegistrationService::class);
        $app->bind(EventRegistrationEligibility::class, DefaultEventRegistrationEligibility::class);
        $app->bind(EventRegistrationScopeResolver::class, DefaultEventRegistrationScopeResolver::class);
        $app->bind(EventRegistrationQuestionResolver::class, DefaultEventRegistrationQuestionResolver::class);
        $app->bind(EventRefundEligibility::class, DefaultEventRefundEligibility::class);

        $app->bind(RegistrationsApi::class, EloquentRegistrationsApi::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(ModelResolver::registrationClass(), EventRegistrationPolicy::class);

        $app->make(Dispatcher::class)
            ->listen(EventRegistrationCancelled::class, CancelBundleChildrenOnParentCanceled::class);
    }

    /**
     * @param  class-string  $model
     */
    private function defaultModelConfig(string $key, string $model): void
    {
        if (config('events.models.' . $key) !== null) {
            return;
        }

        config(['events.models.' . $key => $model]);
    }
}
