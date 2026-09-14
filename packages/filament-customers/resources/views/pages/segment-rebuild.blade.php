<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Automatic Segments
        </x-slot>
        <x-slot name="description">
            Rebuild individual segments or use the header action to rebuild all automatic segments. Rebuilds run in the background queue.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 font-medium">Segment</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 text-right font-medium">Customers</th>
                        <th class="px-3 py-2 text-right font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->getSegments() as $segment)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $segment['name'] }}</td>
                            <td class="px-3 py-2">{{ $segment['type'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($segment['customer_count']) }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($segment['type'] === 'Automatic')
                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        wire:click="rebuildSegment('{{ $segment['id'] }}')"
                                        wire:confirm="Rebuild segment '{{ $segment['name'] }}'?"
                                    >
                                        Rebuild
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No segments found in the current scope.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
