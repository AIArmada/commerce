<?php

declare(strict_types=1);

namespace App\View\Components;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\TrackedProperty;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;
use Throwable;

final class ShopLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $signalsWriteKey = null,
    ) {}

    public function render(): View
    {
        $this->signalsWriteKey = $this->resolveSignalsWriteKey();

        return view('layouts.shop');
    }

    private function resolveSignalsWriteKey(): ?string
    {
        try {
            if (! class_exists(TrackedProperty::class)) {
                return null;
            }

            $trackedProperty = new TrackedProperty;

            if (! Schema::hasTable($trackedProperty->getTable())) {
                return null;
            }

            $owner = OwnerContext::resolve();

            if (! $owner instanceof User) {
                $owner = User::query()
                    ->where('email', 'admin@commerce.demo')
                    ->first();
            }

            return TrackedProperty::query()
                ->when(
                    $owner,
                    fn ($query) => $query->forOwner($owner),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('write_key');
        } catch (Throwable) {
            return null;
        }
    }
}
