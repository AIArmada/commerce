<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Settings\GrowthSettings;
use Spatie\LaravelSettings\Exceptions\MissingSettings;

final readonly class UpdateGrowthSettings
{
    public function handle(bool $enabled): void
    {
        try {
            $settings = app(GrowthSettings::class);
            if ($settings->experimentMiddlewareEnabled === $enabled) {
                return;
            }
        } catch (MissingSettings) {
            $settings = new GrowthSettings(['experimentMiddlewareEnabled' => $enabled]);
        }

        $settings->experimentMiddlewareEnabled = $enabled;
        $settings->save();
    }
}
