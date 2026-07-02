<div class="seating-map">
    @if($showLegend)
        <div class="seating-legend flex flex-wrap gap-3 text-xs mb-3">
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-green-100 border border-green-300"></span> Available</span>
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-yellow-200 border border-yellow-400"></span> Held</span>
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-red-300 border border-red-500"></span> Sold</span>
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-blue-500 text-white border-blue-700"></span> Selected</span>
            <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-gray-300 border border-gray-500"></span> Blocked</span>
        </div>
    @endif

    @php
        $layout = $this->layout;
        $status = $this->status;
    @endphp

    @if(empty($layout['seats']))
        <p class="text-sm text-gray-500">No seats to display.</p>
    @else
        <div class="overflow-x-auto">
            <div
                class="seating-grid inline-grid gap-1"
                style="grid-template-columns: repeat({{ max($layout['bounds']['cols'] ?? 1, 1) }}, minmax(2rem, 2.5rem));"
            >
                @foreach($layout['seats'] as $seat)
                    @php
                        $s = $status[$seat['id']] ?? 'available';
                        $isSelectable = $selectable && $s === 'available';
                        $classes = match(true) {
                            $s === 'sold'     => 'bg-red-300 border-red-500 cursor-not-allowed',
                            $s === 'held'     => 'bg-yellow-200 border-yellow-400 cursor-not-allowed',
                            $s === 'blocked'  => 'bg-gray-300 border-gray-500 cursor-not-allowed',
                            in_array($seat['id'], $picked, true) => 'bg-blue-500 text-white border-blue-700',
                            default           => 'bg-green-100 border-green-300 hover:bg-green-200',
                        };
                    @endphp
                    <button
                        type="button"
                        wire:key="seat-{{ $seat['id'] }}"
                        @if($isSelectable) wire:click="toggleSeat('{{ $seat['id'] }}')" @endif
                        @disabled(!$isSelectable && !in_array($seat['id'], $picked, true))
                        aria-label="Row {{ $seat['row'] }} seat {{ $seat['label'] }}, {{ $s }}"
                        class="seating-seat aspect-square border rounded text-xs flex items-center justify-center transition {{ $classes }}"
                    >
                        {{ $seat['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @if($selectable && count($picked) > 0)
            <div class="mt-3 text-sm">
                {{ count($picked) }} seat(s) selected.
                <button type="button" wire:click="clearSelection" class="ml-2 text-blue-600 underline">Clear</button>
            </div>
        @endif
    @endif
</div>
