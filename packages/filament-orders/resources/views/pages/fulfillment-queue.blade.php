<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Orders Ready for Fulfillment
            </x-slot>

            <x-slot name="description">
                Manage orders that are ready to be shipped. Ship individual orders or use bulk actions for efficiency.
            </x-slot>

            <div class="mt-4">
                {{ $this->table }}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>