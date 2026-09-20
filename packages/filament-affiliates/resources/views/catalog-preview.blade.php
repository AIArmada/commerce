<div class="space-y-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        This is the exact snapshot the affiliate network imports for this program:
        {{ count($snapshot['subjects'] ?? []) }} subject(s) in {{ $snapshot['currency'] ?? '—' }},
        {{ $snapshot['cookie_days'] ?? '—' }}-day cookie.
    </p>

    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="py-1 pr-4 font-medium">Subject</th>
                <th class="py-1 pr-4 font-medium">Type</th>
                <th class="py-1 pr-4 font-medium">Effective rate</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($snapshot['subjects'] ?? [] as $subject)
                <tr>
                    <td class="py-1 pr-4">{{ $subject['title'] ?? $subject['subject_key'] }}</td>
                    <td class="py-1 pr-4">{{ $subject['subject_type'] }}</td>
                    <td class="py-1 pr-4">
                        @if (($subject['effective']['commission_type'] ?? '') === 'fixed')
                            {{ number_format((($subject['effective']['fixed_minor'] ?? 0) / 100), 2) }}
                            {{ $subject['currency'] ?? $snapshot['currency'] }}
                        @else
                            {{ number_format((($subject['effective']['rate_bp'] ?? 0) / 100), 2) }}%
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="py-2 text-gray-400">No subjects: the network will import nothing for this program.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($snapshot['variable_extras']['volume_tiers']))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Plus {{ count($snapshot['variable_extras']['volume_tiers']) }} volume tier(s) and
            {{ count($snapshot['variable_extras']['promotions'] ?? []) }} promotion(s) listed as variable extras.
        </p>
    @endif
</div>
