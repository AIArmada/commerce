<x-filament-panels::page>
    <div class="space-y-6">
        @if($hasError)
            <x-filament::section>
                <div class="flex items-center gap-4 p-4 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                    <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-600" />
                    <div>
                        <h3 class="text-lg font-medium text-danger-800 dark:text-danger-200">{{ __('Unable to fetch webhooks') }}</h3>
                        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Webhook Info --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('About CHIP Webhooks') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Webhooks allow CHIP to send real-time notifications to your application when events occur, 
                    such as successful payments, refunds, or subscription changes.
                </p>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-bolt class="h-5 w-5 text-warning-600" />
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Real-time') }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Instant notifications when events occur</p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-shield-check class="h-5 w-5 text-success-600" />
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Secure') }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Signed payloads for verification</p>
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-2 mb-2">
                            <x-heroicon-o-arrow-path class="h-5 w-5 text-info-600" />
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ __('Reliable') }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Automatic retries on failure</p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Webhooks Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Configured Webhooks') }}
            </x-slot>

            @if(count($webhooks) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ __('ID') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('URL') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Events') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($webhooks as $webhook)
                                <tr class="bg-white dark:bg-gray-900 border-b dark:border-gray-700">
                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ Str::limit($webhook['id'] ?? 'N/A', 20) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="max-w-xs truncate block" title="{{ $webhook['url'] ?? '' }}">
                                            {{ Str::limit($webhook['url'] ?? 'N/A', 50) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if(isset($webhook['event_types']) && is_array($webhook['event_types']))
                                            {{ implode(', ', array_slice($webhook['event_types'], 0, 3)) }}
                                            @if(count($webhook['event_types']) > 3)
                                                <span class="text-gray-400">+{{ count($webhook['event_types']) - 3 }} more</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($webhook['is_active'] ?? false)
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400">
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400">
                                                {{ __('Inactive') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <button 
                                            wire:click="deleteWebhook('{{ $webhook['id'] ?? '' }}')"
                                            wire:confirm="Are you sure you want to delete this webhook?"
                                            class="text-danger-600 hover:text-danger-800 dark:text-danger-400 dark:hover:text-danger-300"
                                        >
                                            <x-heroicon-o-trash class="h-5 w-5" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-bell class="h-12 w-12 text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('No webhooks configured') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ __('Configure webhooks to receive real-time notifications from CHIP.') }}
                    </p>
                </div>
            @endif
        </x-filament::section>

        {{-- Event Types Reference --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('Available Event Types') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="space-y-2">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ __('Purchase Events') }}</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li><code class="text-xs">purchase.paid</code></li>
                        <li><code class="text-xs">purchase.payment_failed</code></li>
                        <li><code class="text-xs">purchase.refunded</code></li>
                        <li><code class="text-xs">purchase.cancelled</code></li>
                    </ul>
                </div>
                <div class="space-y-2">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ __('Subscription Events') }}</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li><code class="text-xs">billing_template.activated</code></li>
                        <li><code class="text-xs">billing_template.cancelled</code></li>
                        <li><code class="text-xs">billing_template_client.added</code></li>
                        <li><code class="text-xs">billing_template_client.removed</code></li>
                    </ul>
                </div>
                <div class="space-y-2">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ __('Send Events') }}</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li><code class="text-xs">send_instruction.completed</code></li>
                        <li><code class="text-xs">send_instruction.failed</code></li>
                        <li><code class="text-xs">bank_account.verified</code></li>
                        <li><code class="text-xs">bank_account.rejected</code></li>
                    </ul>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
