<?php

declare(strict_types=1);

namespace AIArmada\Links\Support;

use AIArmada\Links\Models\Link;
use AIArmada\Links\Rules\DestinationUrlRule;
use Closure;
use Illuminate\Validation\Rule;

final class LinkAttributes
{
    /** @var list<string> */
    public const UTM_KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    /**
     * Ad-platform click IDs forwarded to destinations and captured on clicks.
     *
     * @var list<string>
     */
    public const CLICK_ID_KEYS = [
        'gclid',
        'gbraid',
        'wbraid',
        'dclid',
        'fbclid',
        'msclkid',
        'ttclid',
        'li_fat_id',
        'twclid',
        'snap_click_id',
    ];

    /**
     * @param  list<string>|null  $allowedHosts
     * @return array<string, mixed>
     */
    public static function creationRules(?bool $requireHttps = null, ?array $allowedHosts = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => static::slugRules(),
            'destination_url' => ['required', 'string', 'max:2000', new DestinationUrlRule($requireHttps, $allowedHosts)],
            'utm_defaults' => ['nullable', 'array', static::utmDefaultsRule()],
            'utm_defaults.*' => ['nullable', 'string', 'max:255'],
            'parameters' => ['nullable', 'array', 'max:50'],
            'parameters.*' => ['nullable', 'string', 'max:500'],
            'require_signature' => ['sometimes', 'boolean'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'max_clicks' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'deactivated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @param  list<string>|null  $allowedHosts
     * @return array<string, mixed>
     */
    public static function updateRules(Link $link, ?bool $requireHttps = null, ?array $allowedHosts = null): array
    {
        $rules = static::creationRules($requireHttps, $allowedHosts);

        $rules['name'] = ['sometimes', 'string', 'max:255'];
        $rules['slug'] = static::slugRules($link->getKey());
        $rules['destination_url'] = ['sometimes', 'string', 'max:2000', new DestinationUrlRule($requireHttps, $allowedHosts)];

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private static function slugRules(mixed $ignoreId = null): array
    {
        $table = (string) config('links.database.tables.links', 'tracked_links');
        $reserved = config('links.features.security.reserved_slugs', []);

        $unique = Rule::unique($table, 'slug');

        if (is_string($ignoreId) && $ignoreId !== '') {
            $unique->ignore($ignoreId);
        }

        return [
            'nullable',
            'string',
            'min:3',
            'max:100',
            'alpha_dash',
            Rule::notIn(is_array($reserved) ? $reserved : []),
            $unique,
        ];
    }

    private static function utmDefaultsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $key) {
                if (! in_array($key, static::UTM_KEYS, true)) {
                    $fail("The {$attribute} may only contain utm_* keys.");
                }
            }
        };
    }
}
