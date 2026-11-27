<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Support;

use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\Contacting\Features\Snapshots\Models\ContactSnapshot;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;

final class ResolvesContactingModels
{
    public function contactMethod(): string
    {
        return ContactMethod::class;
    }

    public function socialProfile(): string
    {
        return SocialProfile::class;
    }

    public function contactSnapshot(): string
    {
        return ContactSnapshot::class;
    }
}
