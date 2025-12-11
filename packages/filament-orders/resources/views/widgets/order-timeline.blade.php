<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Order Timeline
        </x-slot>

        <x-slot name="description">
            Complete history of events for this order
        </x-slot>

        <div class="space-y-6">
            <!-- Add Note Form -->
            <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                <h3 class="text-sm font-medium mb-3">Add Note</h3>
                <form wire:submit="addNote">
                    {{ $this->form }}

                    <div class="mt-3">
                        <x-filament::button type="submit" size="sm">
                            <x-heroicon-o-plus class="w-4 h-4 mr-1" />
                            Add Note
                        </x-filament::button>
                    </div>
                </form>
            </div>

            <!-- Timeline -->
            <div class="flow-root">
                <ul role="list" class="-mb-8">
                    @foreach ($this->getTimelineEvents() as $index => $event)
                        <li>
                            <div class="relative pb-8">
                                @if (!$loop->last)
                                    <span class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                                        aria-hidden="true"></span>
                                @endif

                                <div class="relative flex items-start space-x-3">
                                    <div class="relative">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full ring-8 ring-white dark:ring-gray-900
                                                @if($event['color'] === 'success') bg-green-500
                                                @elseif($event['color'] === 'danger') bg-red-500
                                                @elseif($event['color'] === 'warning') bg-yellow-500
                                                @elseif($event['color'] === 'info') bg-blue-500
                                                @else bg-gray-500
                                                @endif
                                            ">
                                            @php
                                                $iconClass = 'h-5 w-5 text-white';
                                            @endphp
                                            @if($event['icon'] === 'heroicon-o-shopping-cart')
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            @elseif($event['icon'] === 'heroicon-o-truck')
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                                </svg>
                                            @elseif($event['icon'] === 'heroicon-o-check-circle')
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @elseif($event['icon'] === 'heroicon-o-credit-card')
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            @elseif($event['icon'] === 'heroicon-o-chat-bubble-left-ellipsis')
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                            @else
                                                <svg class="{{ $iconClass }}" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div>
                                            <div class="text-sm">
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $event['title'] }}
                                                </span>
                                            </div>
                                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $event['timestamp']->format('M d, Y \a\t h:i A') }}
                                                @if(isset($event['causer']))
                                                    <span class="text-gray-400">by {{ $event['causer'] }}</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $event['description'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($this->getTimelineEvents()->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="mt-2">No timeline events yet</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>