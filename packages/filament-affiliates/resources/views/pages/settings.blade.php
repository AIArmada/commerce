<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Commission Settings') }}
        </x-slot>

        <form wire:submit="save" class="space-y-6">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="checkbox"
                    id="multi_level_enabled"
                    wire:model="multi_level_enabled"
                />
                <label for="multi_level_enabled" class="ml-2 text-sm font-medium text-gray-900 dark:text-white">
                    {{ __('Enable Multi-Level Commissions') }}
                </label>
            </x-filament::input.wrapper>

            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    {{ __('Upline Commission Rates') }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    {{ __('Each level is the percentage of the commission that flows to that upline affiliate.') }}
                </p>

                <div class="space-y-3">
                    @foreach ($multi_level_rates as $index => $rate)
                        <div class="flex items-center gap-3">
                            <div class="w-16 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('Level') }} {{ $rate['level'] }}
                            </div>

                            <x-filament::input.wrapper class="flex-1">
                                <x-filament::input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    wire:model="multi_level_rates.{{ $index }}.rate"
                                    class="block w-full"
                                />
                            </x-filament::input.wrapper>

                            <span class="text-sm text-gray-500 w-4">%</span>

                            @if (count($multi_level_rates) > 1)
                                <button
                                    type="button"
                                    wire:click="removeLevel({{ $index }})"
                                    class="text-danger-600 hover:text-danger-500 text-xl leading-none"
                                >
                                    &times;
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="addLevel"
                    class="mt-3 text-sm font-semibold text-primary-600 hover:text-primary-500"
                >
                    + {{ __('Add level') }}
                </button>
            </div>

            <x-filament::button type="submit">
                {{ __('Save') }}
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
