<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Model;

final class DefaultEventDisplayTimezoneResolver implements EventDisplayTimezoneResolver
{
    public function resolve(Model $record, ?Model $viewer = null): string
    {
        $viewerTimezone = $this->timezoneFromModel($viewer, 'timezone')
            ?? $this->timezoneFromModel($viewer, 'default_timezone');

        if ($viewerTimezone !== null) {
            return $viewerTimezone;
        }

        if ($record instanceof Occurrence) {
            return $this->timezoneFromModel($record, 'timezone')
                ?? $this->timezoneFromModel($record->event, 'default_timezone')
                ?? $this->configuredDefaultTimezone();
        }

        if ($record instanceof Event) {
            return $this->timezoneFromModel($record, 'default_timezone')
                ?? $this->configuredDefaultTimezone();
        }

        return $this->configuredDefaultTimezone();
    }

    private function timezoneFromModel(?Model $model, string $attribute): ?string
    {
        if ($model === null) {
            return null;
        }

        $value = $model->getAttribute($attribute);

        if (! is_string($value)) {
            return null;
        }

        $timezone = mb_trim($value);

        return $timezone !== '' ? $timezone : null;
    }

    private function configuredDefaultTimezone(): string
    {
        $timezone = config('events.timezone.default');

        if (is_string($timezone) && mb_trim($timezone) !== '') {
            return mb_trim($timezone);
        }

        return 'UTC';
    }
}
