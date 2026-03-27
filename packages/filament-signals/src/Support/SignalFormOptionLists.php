<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

final class SignalFormOptionLists
{
    /**
     * @return list<string>
     */
    public static function conditionFields(): array
    {
        $fields = [
            'path',
            'url',
            'source',
            'medium',
            'campaign',
            'referrer',
            'event_name',
            'event_category',
            'properties.conversion_type',
            'properties.goal_slug',
            'properties.method',
            'properties.checkout.gateway',
        ];

        if (config('signals.features.monetary.enabled', true)) {
            $fields[] = 'currency';
            $fields[] = 'revenue_minor';
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public static function eventNames(): array
    {
        return [
            'page_view',
            'affiliate.attributed',
            'affiliate.conversion.recorded',
            'auth.login',
            'auth.registered',
        ];
    }

    /**
     * @return list<string>
     */
    public static function eventCategories(): array
    {
        $categories = [
            'acquisition',
            'auth',
            'content',
            'conversion',
            'engagement',
        ];

        if (config('signals.features.monetary.enabled', true)) {
            $categories[] = 'revenue';
        }

        return $categories;
    }
}
