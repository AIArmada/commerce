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
        return [
            'path',
            'url',
            'source',
            'medium',
            'campaign',
            'referrer',
            'currency',
            'event_name',
            'event_category',
            'revenue_minor',
            'properties.conversion_type',
            'properties.goal_slug',
            'properties.method',
            'properties.checkout.gateway',
        ];
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
        return [
            'acquisition',
            'auth',
            'content',
            'conversion',
            'engagement',
            'revenue',
        ];
    }
}
