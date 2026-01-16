<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->getStats() as $stat)
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0">
                        <x-filament::icon
                            :icon="$stat->getIcon()"
                            class="h-8 w-8 text-gray-400"
                        />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $stat->getLabel() }}
                        </p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $stat->getValue() }}
                        </p>
                        @if ($stat->getDescription())
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $stat->getDescription() }}
                            </p>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Applications --}}
        <x-filament::section>
            <x-slot name="heading">
                Pending Applications
            </x-slot>

            @if ($this->getRecentApplications()->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No pending applications.
                </p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($this->getRecentApplications() as $application)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $application->offer?->name ?? 'Unknown Offer' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $application->affiliate?->code ?? 'Unknown Affiliate' }}
                                    &mdash; {{ $application->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <x-filament::badge color="warning">
                                Pending
                            </x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Top Offers --}}
        <x-filament::section>
            <x-slot name="heading">
                Top Offers
            </x-slot>

            @if ($this->getTopOffers()->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No active offers yet.
                </p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($this->getTopOffers() as $offer)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $offer->name }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $offer->site?->name ?? 'Unknown Site' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $offer->applications_count }} affiliates
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
