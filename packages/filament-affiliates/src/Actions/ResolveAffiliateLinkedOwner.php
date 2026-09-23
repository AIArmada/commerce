<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Foundation\Auth\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class ResolveAffiliateLinkedOwner
{
    use AsAction;

    /**
     * Resolve the affiliate owner tuple from the `linked_user` form value.
     *
     * The `owner_type` / `owner_id` inputs are never trusted: they are stripped
     * and re-derived server-side from the selected user id, which must exist.
     * An empty selection leaves ownership untouched (create: context
     * auto-assign or global; edit: owners are immutable after creation).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data, ?Affiliate $record = null): array
    {
        $linkedUserId = $data['linked_user'] ?? null;

        unset($data['linked_user'], $data['owner_type'], $data['owner_id']);

        if ($linkedUserId === null || $linkedUserId === '') {
            return $data;
        }

        if (! is_string($linkedUserId) && ! is_int($linkedUserId)) {
            throw ValidationException::withMessages([
                'data.linked_user' => 'The selected linked user is invalid.',
            ]);
        }

        $userModel = (string) config('auth.providers.users.model', User::class);
        $userId = (string) $linkedUserId;

        if (! $userModel::query()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                'data.linked_user' => 'The selected linked user could not be found.',
            ]);
        }

        $data['owner_type'] = (new $userModel)->getMorphClass();
        $data['owner_id'] = $userId;

        return $data;
    }
}
