<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

final class FilamentAffiliates
{
    public static function make(): FilamentAffiliatesPlugin
    {
        return FilamentAffiliatesPlugin::make();
    }

    public static function get(): FilamentAffiliatesPlugin
    {
        return FilamentAffiliatesPlugin::get();
    }
}
