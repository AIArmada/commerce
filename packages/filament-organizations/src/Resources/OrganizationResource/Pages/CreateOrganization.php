<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrganizations\Resources\OrganizationResource\Pages;

use AIArmada\FilamentOrganizations\Resources\OrganizationResource;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    public static function creationThrottleKey(Model $actor): string
    {
        return sprintf('filament-organizations.create.%s', (string) $actor->getKey());
    }

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Filament::auth()->user();

        abort_unless($actor instanceof Model, 403);

        $maxAttempts = (int) config('filament-organizations.rate_limits.create_per_hour', 10);
        $key = self::creationThrottleKey($actor);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'name' => 'Too many organizations created recently. Please try again later.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        return app(CreateOrganizationAction::class)->handle($actor, $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Organization created';
    }
}
