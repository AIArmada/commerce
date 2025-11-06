<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-ticket"
        heading="Quick Apply Voucher"
        description="Enter a voucher code to instantly apply it to this cart"
        collapsible
    >
        <form wire:submit="applyVoucher" class="space-y-4">
            {{ $this->form }}

            <div class="flex items-center gap-3">
                <x-filament::button
                    type="submit"
                    color="success"
                    icon="heroicon-o-ticket"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Apply Voucher</span>
                    <span wire:loading>Applying...</span>
                </x-filament::button>

                @if($voucherCode !== '')
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="$set('voucherCode', '')"
                        outlined
                    >
                        Clear
                    </x-filament::button>
                @endif
            </div>
        </form>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex items-start gap-2">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="h-4 w-4 mt-0.5"
                />
                <div>
                    <p>Enter your voucher code above and click "Apply" or press Enter.</p>
                    <p class="mt-1">You'll see the discount reflected in your cart total immediately.</p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
