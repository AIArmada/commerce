<x-filament-widgets::widget>
    <x-filament::section heading="Transaction Timeline">
        @if($transactions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No transactions yet.</p>
        @else
            <div class="space-y-4">
                @foreach($transactions as $transaction)
                    <div class="flex items-start gap-4 border-l-2 pl-4 pb-4 {{ $transaction['is_credit'] ? 'border-green-500' : 'border-red-500' }}">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                    {{ $transaction['is_credit'] ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' }}">
                                    {{ $transaction['type'] }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400" title="{{ $transaction['created_at_full'] }}">
                                    {{ $transaction['created_at'] }}
                                </span>
                            </div>
                            <div class="mt-1 flex items-center justify-between">
                                <span class="text-lg font-semibold {{ $transaction['is_credit'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $transaction['is_credit'] ? '+' : '-' }}{{ $transaction['amount'] }}
                                </span>
                                <span class="text-sm text-gray-600 dark:text-gray-300">
                                    Balance: {{ $transaction['balance_after'] }}
                                </span>
                            </div>
                            @if($transaction['description'])
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $transaction['description'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
