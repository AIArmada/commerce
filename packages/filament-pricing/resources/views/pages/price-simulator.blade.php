<x-filament-panels::page>
    <form wire:submit="calculate">
        {{ $this->form }}
    </form>

    @if ($result)
        <div class="mt-6">
            {{ $this->resultInfolist }}
        </div>
    @else
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">
                    Ready to Calculate
                </x-slot>

                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <p class="mt-4 text-sm">
                        Fill in the parameters above and click "Calculate Price" to see the pricing breakdown
                    </p>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>