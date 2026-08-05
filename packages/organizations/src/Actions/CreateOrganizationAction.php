<?php

declare(strict_types=1);

namespace AIArmada\Organizations\Actions;

use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Contracts\OrganizationLifecycleHook;
use AIArmada\Organizations\Enums\OrganizationStatus;
use AIArmada\Organizations\Enums\OrganizationVisibility;
use AIArmada\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateOrganizationAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $creator, array $attributes = []): Organization
    {
        return DB::transaction(function () use ($attributes, $creator): Organization {
            $name = mb_trim((string) ($attributes['name'] ?? ''));

            if ($name === '') {
                throw new InvalidArgumentException('An organization name is required.');
            }

            $slug = $this->uniqueSlug((string) ($attributes['slug'] ?? Str::slug($name)));
            $now = now();

            $organization = Organization::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $attributes['description'] ?? null,
                'status' => OrganizationStatus::Active,
                'visibility' => OrganizationVisibility::Private,
                'created_by' => (string) $creator->getKey(),
                'last_state_change_at' => $now,
            ]);

            app(AddMemberAction::class)->handle($organization, $creator, MemberRole::Owner);
            app(OrganizationLifecycleHook::class)->created($organization, $creator);

            return $organization;
        });
    }

    private function uniqueSlug(string $slug): string
    {
        $base = mb_trim($slug) !== '' ? Str::slug($slug) : Str::lower((string) Str::uuid());
        $candidate = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
