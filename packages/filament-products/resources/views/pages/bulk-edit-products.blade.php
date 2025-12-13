<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Bulk Edit Products
        </x-slot>

        <x-slot name="description">
            Select products and use bulk actions to update prices, statuses, visibility, or categories efficiently.
        </x-slot>

        <div class="mt-4">
            {{ $this->table }}
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Available Bulk Actions
        </x-slot>

        <div class="prose dark:prose-invert max-w-none">
            <ul>
                <li><strong>Update Price:</strong> Set new prices, or increase/decrease by percentage or fixed amount</li>
                <li><strong>Change Status:</strong> Move products between active, draft, and archived states</li>
                <li><strong>Change Visibility:</strong> Control where products appear (catalog, search, or hidden)</li>
                <li><strong>Assign Categories:</strong> Add or replace product categories</li>
            </ul>

            <p class="text-sm text-gray-500 mt-4">
                <strong>Tip:</strong> Use filters to narrow down products before selecting them for bulk editing.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>