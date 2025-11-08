<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Console;

use Illuminate\Console\GeneratorCommand;

class GeneratePoliciesCommand extends GeneratorCommand
{
    protected $name = 'permissions:generate-policies';

    protected $description = 'Scaffold policy classes mapping CRUD abilities to permission strings.';

    protected $type = 'Policy';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/policy.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Policies';
    }
}
