<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Targeting Configuration
        </x-slot>
        <x-slot name="description">
            Configure voucher targeting rules and presets for precise discount targeting.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                @foreach ($this->getFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </form>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Available Targeting Rule Types
        </x-slot>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>User Segment</strong>
                <p class="text-gray-500 text-xs">Target by customer segment (VIP, new, etc.)</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Cart Value</strong>
                <p class="text-gray-500 text-xs">Minimum/maximum cart value requirements</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Cart Quantity</strong>
                <p class="text-gray-500 text-xs">Number of items in cart</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Product in Cart</strong>
                <p class="text-gray-500 text-xs">Require specific products</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Category in Cart</strong>
                <p class="text-gray-500 text-xs">Require specific categories</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Time Window</strong>
                <p class="text-gray-500 text-xs">Valid only during certain hours</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Day of Week</strong>
                <p class="text-gray-500 text-xs">Valid only on specific days</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>Channel</strong>
                <p class="text-gray-500 text-xs">Web, mobile, POS, etc.</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">
                <strong>First Purchase</strong>
                <p class="text-gray-500 text-xs">New customer only discounts</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
