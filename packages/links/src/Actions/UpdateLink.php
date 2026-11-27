<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Events\LinkUpdated;
use AIArmada\Links\Models\Link;
use AIArmada\Links\Persistence\LockOwnedLink;
use AIArmada\Links\Support\LinkAttributes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateLink
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $allowedHosts
     */
    public function handle(Link $link, array $attributes, ?bool $requireHttps = null, ?array $allowedHosts = null): Link
    {
        $updated = $link->getConnection()->transaction(function () use ($link, $attributes, $requireHttps, $allowedHosts): Link {
            $link = app(LockOwnedLink::class)->handle($link);
            $validated = Validator::make($attributes, LinkAttributes::updateRules($link, $requireHttps, $allowedHosts))->validate();

            foreach (['subject_type', 'subject_id'] as $identity) {
                if (! array_key_exists($identity, $attributes)) {
                    continue;
                }

                $requested = $attributes[$identity] === null ? null : (string) $attributes[$identity];
                $current = $link->getAttribute($identity) === null ? null : (string) $link->getAttribute($identity);

                if ($requested !== $current) {
                    throw ValidationException::withMessages([
                        $identity => 'The tracked-link subject identity cannot be changed after creation.',
                    ]);
                }
            }

            unset($validated['subject_type'], $validated['subject_id']);

            if (! is_string($validated['slug'] ?? null) || ($validated['slug'] ?? '') === '') {
                unset($validated['slug']);
            }

            $link->update($validated);

            return $link;
        });

        event(new LinkUpdated($updated));

        $link->setRawAttributes($updated->getAttributes(), true);

        return $updated;
    }
}
