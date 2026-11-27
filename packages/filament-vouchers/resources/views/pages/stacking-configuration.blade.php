<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Stacking Configuration
        </x-slot>
        <x-slot name="description">
            Current application configuration. Change config/vouchers.php to update these settings.
        </x-slot>

        {{ $this->form }}
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Active Stacking Rules Summary
        </x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <ul>
                <li><strong>Mode:</strong> {{ config('vouchers.stacking.mode', 'sequential') }}</li>
                <li><strong>Auto-Optimize:</strong> {{ config('vouchers.stacking.auto_optimize', false) ? 'Yes' : 'No' }}</li>
                <li><strong>Auto-Replace:</strong> {{ config('vouchers.stacking.auto_replace', true) ? 'Yes' : 'No' }}</li>
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
