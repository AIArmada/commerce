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
     * @return array<string, mixed>
     */
    public static function creationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => static::slugRules(),
            'destination_url' => ['required', 'string', 'max:2000', new DestinationUrlRule],
            'utm_defaults' => ['nullable', 'array', static::utmDefaultsRule()],
            'utm_defaults.*' => ['nullable', 'string', 'max:255'],
            'max_clicks' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'deactivated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(Link $link): array
    {
        $rules = static::creationRules();

        $rules['name'] = ['sometimes', 'string', 'max:255'];
        $rules['slug'] = static::slugRules($link->getKey());
        $rules['destination_url'] = ['sometimes', 'string', 'max:2000', new DestinationUrlRule];

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
