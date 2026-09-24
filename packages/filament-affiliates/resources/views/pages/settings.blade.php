<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('Commission Settings') }}
        </x-slot>

        <form wire:submit="save" class="space-y-6">
            <div class="flex items-center gap-3">
                <x-filament::input.checkbox
                    id="multi_level_enabled"
                    wire:model="multi_level_enabled"
                />
                <label for="multi_level_enabled" class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('Enable Multi-Level Commissions') }}
                </label>
            </div>

            <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ __('Upline Commission Rates') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Each level is the percentage of the commission that flows to that upline affiliate.') }}
                </p>

                <div class="mt-4 space-y-3">
                    @foreach ($multi_level_rates as $index => $rate)
                        <div wire:key="level-{{ $index }}" class="flex items-center gap-3">
                            <div class="w-20 shrink-0 text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ __('Level') }} {{ $rate['level'] }}
                            </div>

                            <x-filament::input.wrapper suffix="%" class="min-w-0 flex-1">
                                <x-filament::input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    wire:model="multi_level_rates.{{ $index }}.rate"
                                    class="block w-full"
                                />
                            </x-filament::input.wrapper>

                            @if (count($multi_level_rates) > 1)
                                <x-filament::icon-button
                                    icon="heroicon-m-x-mark"
                                    color="danger"
                                    size="sm"
                                    :label="__('Remove level')"
                                    :tooltip="__('Remove level')"
                                    wire:click="removeLevel({{ $index }})"
                                />
                            @endif
                        </div>
                    @endforeach
                </div>

                <x-filament::button
                    type="button"
                    outlined
                    size="sm"
                    icon="heroicon-m-plus"
                    wire:click="addLevel"
                    class="mt-4"
                >
                    {{ __('Add level') }}
                </x-filament::button>
            </div>

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    {{ __('Save') }}
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
