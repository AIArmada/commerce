<x-filament-panels::page>
    {{-- Search & Filters --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-1 gap-4">
            <x-filament::input.wrapper class="w-full max-w-md">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search offers..."
                />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper class="w-48">
                <x-filament::input.select wire:model.live="categoryFilter">
                    <option value="">All Categories</option>
                    @foreach ($this->getCategories() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    {{-- Offers Grid --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($this->getOffers() as $offer)
            <x-filament::section class="relative">
                @if ($offer->is_featured)
                    <div class="absolute -right-1 -top-1">
                        <x-filament::badge color="warning">
                            Featured
                        </x-filament::badge>
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $offer->name }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $offer->site?->name ?? 'Unknown Site' }}
                        </p>
                    </div>

                    @if ($offer->description)
                        <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ $offer->description }}
                        </p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        @if ($offer->category)
                            <x-filament::badge color="gray">
                                {{ $offer->category->name }}
                            </x-filament::badge>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        @if (config('filament-affiliate-network.marketplace.show_commission_rates', true))
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Commission</p>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    @if ($offer->commission_type === 'percentage')
                                        {{ number_format($offer->commission_rate / 100, 2) }}%
                                    @else
                                        ${{ number_format($offer->commission_rate / 100, 2) }}
                                    @endif
                                </p>
                            </div>
                        @endif

                        @if (config('filament-affiliate-network.marketplace.show_cookie_duration', true) && $offer->cookie_days)
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Cookie</p>
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ $offer->cookie_days }} days
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-2 pt-2">
                        @php
                            $status = $this->getApplicationStatus($offer);
                        @endphp

                        @if ($status === 'approved')
                            <x-filament::button
                                wire:click="generateLink('{{ $offer->id }}')"
                                size="sm"
                                color="success"
                                class="flex-1"
                            >
                                Get Link
                            </x-filament::button>
                        @elseif ($status === 'pending')
                            <x-filament::button
                                disabled
                                size="sm"
                                color="warning"
                                class="flex-1"
                            >
                                Pending Approval
                            </x-filament::button>
                        @elseif ($status === 'rejected')
                            <x-filament::button
                                wire:click="applyForOffer('{{ $offer->id }}')"
                                size="sm"
                                color="gray"
                                class="flex-1"
                            >
                                Reapply
                            </x-filament::button>
                        @else
                            <x-filament::button
                                wire:click="applyForOffer('{{ $offer->id }}')"
                                size="sm"
                                class="flex-1"
                            >
                                {{ $offer->requires_approval ? 'Apply' : 'Get Link' }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @empty
            <div class="col-span-full">
                <x-filament::section>
                    <div class="py-12 text-center">
                        <x-heroicon-o-gift class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            No offers found
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Try adjusting your search or filter criteria.
                        </p>
                    </div>
                </x-filament::section>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
