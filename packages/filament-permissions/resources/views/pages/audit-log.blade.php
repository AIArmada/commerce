<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Statistics --}}
        @php $stats = $this->getStatistics(); @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                <p class="text-sm text-gray-500">Total Events</p>
            </div>
            
            @foreach($stats['by_severity'] as $severity => $count)
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <p class="text-2xl font-bold {{ match($severity) {
                        'critical' => 'text-red-600',
                        'high' => 'text-orange-600',
                        'medium' => 'text-yellow-600',
                        default => 'text-gray-600',
                    } }}">{{ $count }}</p>
                    <p class="text-sm text-gray-500 capitalize">{{ $severity }}</p>
                </div>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-2">
            @if($eventTypeFilter || $severityFilter)
                <button 
                    wire:click="clearFilters"
                    class="px-3 py-1 text-sm rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600"
                >
                    Clear Filters
                </button>
            @endif
        </div>

        {{-- Log Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Time</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Event</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Severity</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Description</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Actor</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                    {{ $log->created_at->format('M d, H:i:s') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
                                        {{ $log->event_type->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full {{ match($log->severity->value) {
                                        'critical' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        'high' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                                        'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    } }}">
                                        {{ $log->severity->value }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ Str::limit($log->description, 60) }}
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $log->actor_id ?? 'System' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-500 font-mono text-xs">
                                    {{ $log->ip_address ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No audit logs found for the selected period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
