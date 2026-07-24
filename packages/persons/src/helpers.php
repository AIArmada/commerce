<?php

declare(strict_types=1);

use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Support\ModelResolver;
use Illuminate\Database\Eloquent\Relations\Relation;

if (! function_exists('persons_table')) {
    /**
     * Resolve a persons package table name from config.
     */
    function persons_table(string $key, string $default): string
    {
        return config('persons.database.tables.' . $key, $default);
    }
}

if (! function_exists('persons_register_morph_map')) {
    /**
     * Register the morph alias for the configured Person model so assignment
     * junction tables store a short, stable type string instead of a FQCN.
     *
     * Call once from the host application's ServiceProvider boot(), e.g.
     * `persons_register_morph_map('person');`
     */
    function persons_register_morph_map(string $alias = 'person'): void
    {
        Relation::morphMap([$alias => ModelResolver::personClass()]);
    }
}
