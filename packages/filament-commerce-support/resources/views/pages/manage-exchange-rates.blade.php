<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
    </form>

    @if ($history !== [])
        <div class="mt-6">
            <h2 class="text-base font-semibold">{{ __('Rate history') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Dated snapshots used for historical conversion. The latest snapshot on or before a report date wins.') }}</p>

            <div class="mt-3 space-y-3">
                @foreach ($history as $date => $snapshot)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-sm font-medium">{{ $date }}</div>
                        <div class="mt-1 text-sm text-gray-500">
                            {{ collect($snapshot)->map(fn ($value, $code) => $code.' => '.$value)->implode(', ') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
