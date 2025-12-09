<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="fi-section-content-ctn">
                <div class="fi-section-content p-6">
                    <div class="text-center">
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="mx-auto h-12 w-12 text-warning-500"
                        />
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ __('filament-cashier::gateway.setup.title') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('filament-cashier::gateway.setup.description') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($this->getGateways() as $gateway)
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="fi-section-content-ctn">
                        <div class="fi-section-content p-6">
                            <div class="flex items-start gap-4">
                                @if ($gateway['available'])
                                    <x-filament::icon
                                        icon="heroicon-o-check-circle"
                                        class="h-8 w-8 text-success-500 flex-shrink-0"
                                    />
                                @else
                                    <x-filament::icon
                                        icon="heroicon-o-x-circle"
                                        class="h-8 w-8 text-danger-500 flex-shrink-0"
                                    />
                                @endif

                                <div class="flex-1">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $gateway['name'] }}
                                        @if ($gateway['available'])
                                            <span class="ml-2 inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/30">
                                                {{ __('filament-cashier::gateway.status.available') }}
                                            </span>
                                        @else
                                            <span class="ml-2 inline-flex items-center rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 ring-1 ring-inset ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/30">
                                                {{ __('filament-cashier::gateway.status.unavailable') }}
                                            </span>
                                        @endif
                                    </h4>

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $gateway['description'] }}
                                    </p>

                                    @unless ($gateway['available'])
                                        <div class="mt-4">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Install with:</p>
                                            <code class="block rounded-md bg-gray-100 px-3 py-2 text-sm font-mono text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                                {{ $gateway['install'] }}
                                            </code>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
