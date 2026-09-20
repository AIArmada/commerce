<x-filament-panels::page>
    {{ $this->form }}

    @if ($previewMonth !== null)
        <div class="mt-2">
            <h2 class="text-base font-semibold">{{ __('Preview for :month', ['month' => $previewMonth]) }}</h2>

            @if ($preview === [])
                <p class="mt-1 text-sm text-gray-500">{{ __('No bonuses calculate for this month.') }}</p>
            @else
                <div class="mt-3 space-y-3">
                    @foreach ($preview as $type => $row)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <div class="text-sm font-medium">{{ $type }} &middot; {{ $row['count'] }} bonus(es)</div>
                            <div class="mt-1 text-sm text-gray-500">
                                {{ collect($row['totals'])->map(fn ($value, $code) => $code.' '.number_format($value / 100, 2))->implode(', ') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
