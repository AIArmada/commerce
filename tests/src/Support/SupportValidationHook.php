<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Support;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Livewire\Drawer\Utils;
use Livewire\Features\SupportValidation\SupportValidation;

final class SupportValidationHook extends SupportValidation
{
    public function render($view, $data)
    {
        $bag = $this->component->getErrorBag();

        if (! $bag instanceof MessageBag) {
            $bag = new MessageBag((array) ($bag ?? []));
            $this->component->setErrorBag($bag);
        }

        $errors = (new ViewErrorBag())->put('default', $bag);

        $revert = Utils::shareWithViews('errors', $errors);

        return static function () use ($revert): void {
            $revert();
        };
    }
}
