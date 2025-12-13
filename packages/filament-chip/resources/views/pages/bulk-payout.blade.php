<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Bulk Payout Form --}}
        <form wire:submit.prevent="processBulkPayouts">
            {{ $this->form }}
        </form>

        {{-- Results Section --}}
        @if($hasProcessed && !empty($results))
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Processing Results') }}
                </x-slot>

                <div class="space-y-3">
                    @foreach($results as $result)
                        <div class="flex items-center justify-between p-3 rounded-lg {{ $result['status'] === 'success' ? 'bg-success-50 dark:bg-success-900/20' : 'bg-danger-50 dark:bg-danger-900/20' }}">
                            <div class="flex items-center gap-3">
                                @if($result['status'] === 'success')
                                    <x-heroicon-o-check-circle class="h-5 w-5 text-success-600" />
                                @else
                                    <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600" />
                                @endif
                                <div>
                                    <div class="font-medium {{ $result['status'] === 'success' ? 'text-success-800 dark:text-success-200' : 'text-danger-800 dark:text-danger-200' }}">
                                        {{ $result['reference'] }}
                                    </div>
                                    <div class="text-sm {{ $result['status'] === 'success' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                                        {{ $result['message'] }}
                                    </div>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded {{ $result['status'] === 'success' ? 'bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100' : 'bg-danger-100 text-danger-800 dark:bg-danger-800 dark:text-danger-100' }}">
                                {{ ucfirst($result['status']) }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <x-filament::button wire:click="clearResults" color="gray" outlined>
                        {{ __('Clear Results') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Info Section --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('Bulk Payout Guidelines') }}
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-info-50 dark:bg-info-900/20 rounded-lg">
                        <h4 class="font-medium text-info-800 dark:text-info-200 flex items-center gap-2">
                            <x-heroicon-o-information-circle class="h-5 w-5" />
                            {{ __('Before Processing') }}
                        </h4>
                        <ul class="text-sm text-info-700 dark:text-info-300 mt-2 space-y-1">
                            <li>• Verify all bank accounts are approved</li>
                            <li>• Double-check amounts and references</li>
                            <li>• Ensure notification emails are correct</li>
                            <li>• Confirm sufficient account balance</li>
                        </ul>
                    </div>
                    <div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                        <h4 class="font-medium text-warning-800 dark:text-warning-200 flex items-center gap-2">
                            <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                            {{ __('Limitations') }}
                        </h4>
                        <ul class="text-sm text-warning-700 dark:text-warning-300 mt-2 space-y-1">
                            <li>• Maximum 50 payouts per batch</li>
                            <li>• Minimum amount: RM 0.01</li>
                            <li>• Only verified bank accounts</li>
                            <li>• Processing may take several minutes</li>
                        </ul>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
