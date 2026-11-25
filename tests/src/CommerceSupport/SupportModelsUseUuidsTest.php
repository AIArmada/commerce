<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Features\Reporting\Models\Report;
use AIArmada\CommerceSupport\Features\UserPreferences\Models\NotificationPreference;
use AIArmada\CommerceSupport\Features\UserPreferences\Models\SavedSearch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

it('uses uuid identifiers for commerce support persisted models', function (string $modelClass): void {
    expect(class_uses_recursive($modelClass))->toHaveKey(HasUuids::class);
})->with([
    NotificationPreference::class,
    Report::class,
    SavedSearch::class,
]);
