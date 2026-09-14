<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateOrganizationAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $creator, array $attributes = []): Organization
    {
        $name = $attributes['name'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw new InvalidArgumentException('An organization name is required.');
        }

        $name = mb_trim($name);

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('The organization name may not be longer than 255 characters.');
        }

        $slug = $attributes['slug'] ?? null;

        if ($slug !== null && ! is_string($slug)) {
            throw new InvalidArgumentException('The organization slug must be a string.');
        }

        if (is_string($slug) && mb_strlen($slug) > 255) {
            throw new InvalidArgumentException('The organization slug may not be longer than 255 characters.');
        }

        $description = $attributes['description'] ?? null;

        if ($description !== null && ! is_string($description)) {
            throw new InvalidArgumentException('The organization description must be a string.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($creator, $description, $name, $slug): Organization {
                    $slug = $this->uniqueSlug($slug ?? Str::slug($name));
                    $now = CarbonImmutable::now();

                    $organization = new Organization([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                    ]);
                    $organization->forceFill([
                        'status' => OrganizationStatus::Active,
                        'visibility' => OrganizationVisibility::Private,
                        'created_by' => (string) $creator->getKey(),
                        'last_state_change_at' => $now,
                    ]);
                    $organization->save();

                    app(AddMemberAction::class)->handle($organization, $creator, MemberRole::Owner);
                    app(OrganizationLifecycleHook::class)->created($organization, $creator);

                    return $organization;
                });
            } catch (QueryException $exception) {
                if (! $this->isSlugUniquenessViolation($exception) || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Organization creation failed after retrying slug uniqueness.');
    }

    private function uniqueSlug(string $slug): string
    {
        $base = mb_trim($slug) !== '' ? Str::slug($slug) : Str::lower((string) Str::uuid());
        $base = mb_substr($base, 0, 250);
        $candidate = $base;
        $suffix = 2;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (! Organization::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }

            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        throw new LogicException('Organization slug generation exhausted its collision budget.');
    }

    private function isSlugUniquenessViolation(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && Str::contains($message, 'slug');
    }
}
