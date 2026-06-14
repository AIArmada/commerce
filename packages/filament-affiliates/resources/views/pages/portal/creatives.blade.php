<x-filament-panels::page>
    @if (! $hasAffiliate)
        <div class="flex flex-col items-center justify-center py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
            <div class="p-4 bg-primary-50 dark:bg-primary-900/30 rounded-full mb-4">
                <x-heroicon-o-user-plus class="w-12 h-12 text-primary-500" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white text-center mb-2">{{ __('No Affiliate Account') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm">{{ __('You need an active affiliate account to access promotional marketing materials.') }}</p>
        </div>
    @else
        @php
            $param = config('affiliates.links.parameter', 'aff');
            $defaultAffiliateLink = mb_rtrim((string) config('app.url'), '/') . '?' . $param . '=' . $affiliateCode;

            $assetsForJs = $assets->map(fn (array $asset): array => [
                ...$asset,
                'search' => mb_strtolower(implode(' ', [
                    $asset['title'],
                    $asset['description'],
                    $asset['category'],
                    $asset['campaign'],
                    $asset['program'],
                    $asset['format'],
                    implode(' ', $asset['platforms']),
                ])),
            ])->values();
        @endphp

        <div
            x-data="affiliateMediaLibrary({
                assets: @js($assetsForJs),
                defaultAffiliateUrl: @js($defaultAffiliateLink),
            })"
            class="space-y-6"
        >
            {{-- Header --}}
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ __('Affiliate Media Library') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Choose ready-to-use marketing materials, add your affiliate link, and start promoting.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button
                        color="gray"
                        icon="heroicon-m-link"
                        x-on:click="copyText(defaultAffiliateUrl, '{{ __('Affiliate link copied') }}')"
                    >
                        {{ __('Copy My Link') }}
                    </x-filament::button>
                    <x-filament::button
                        icon="heroicon-m-arrow-down-tray"
                        x-bind:disabled="selected.length === 0"
                        x-on:click="downloadSelected()"
                    >
                        {{ __('Download Selected') }}
                        <span x-show="selected.length" x-text="`(${selected.length})`"></span>
                    </x-filament::button>
                </div>
            </div>

            {{-- Summary cards --}}
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Total assets') }}</p>
                            <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white" x-text="assets.length"></p>
                        </div>
                        <div class="rounded-lg bg-primary-50 p-2.5 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-folder-open" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Most downloaded') }}</p>
                            <p class="mt-2 truncate text-lg font-bold text-gray-950 dark:text-white" x-text="mostDownloaded?.title"></p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="mostDownloaded?.downloads.toLocaleString()"></span> {{ __('downloads') }}
                            </p>
                        </div>
                        <div class="rounded-lg bg-blue-50 p-2.5 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400">
                            <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('New assets') }}</p>
                            <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white" x-text="newAssetsCount"></p>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-2.5 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400">
                            <x-filament::icon icon="heroicon-o-sparkles" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Active campaigns') }}</p>
                            <p class="mt-2 text-3xl font-bold text-gray-950 dark:text-white" x-text="campaignCount"></p>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-2.5 text-purple-600 dark:bg-purple-400/10 dark:text-purple-400">
                            <x-filament::icon icon="heroicon-o-megaphone" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    <label class="relative md:col-span-2">
                        <span class="sr-only">{{ __('Search') }}</span>
                        <x-filament::icon
                            icon="heroicon-m-magnifying-glass"
                            class="pointer-events-none absolute left-3 top-1/2 z-10 h-5 w-5 -translate-y-1/2 text-gray-400"
                        />
                        <input
                            x-model.debounce.250ms="search"
                            type="search"
                            placeholder="{{ __('Search media...') }}"
                            class="block w-full rounded-lg border-0 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500"
                        />
                    </label>

                    <select x-model="category"
                        class="rounded-lg border-0 bg-white py-2.5 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>

                    <select x-model="campaign"
                        class="rounded-lg border-0 bg-white py-2.5 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="">{{ __('All campaigns') }}</option>
                        @foreach ($campaigns as $camp)
                            <option value="{{ $camp }}">{{ $camp }}</option>
                        @endforeach
                    </select>

                    <select x-model="format"
                        class="rounded-lg border-0 bg-white py-2.5 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="">{{ __('All formats') }}</option>
                        @foreach ($formats as $fmt)
                            <option value="{{ $fmt }}">{{ $fmt }}</option>
                        @endforeach
                    </select>

                    <select x-model="program"
                        class="rounded-lg border-0 bg-white py-2.5 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="">{{ __('All programs') }}</option>
                        @foreach ($programs as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                        @endforeach
                    </select>

                    <select x-model="platform"
                        class="rounded-lg border-0 bg-white py-2.5 text-sm text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10"
                    >
                        <option value="">{{ __('All platforms') }}</option>
                        @foreach ($platforms as $plt)
                            <option value="{{ $plt }}">{{ $plt }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Showing') }}
                        <span class="font-semibold text-gray-950 dark:text-white" x-text="filteredAssets.length"></span>
                        {{ __('promotional assets') }}
                    </p>
                    <button
                        type="button"
                        x-show="hasFilters"
                        x-on:click="resetFilters()"
                        class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        {{ __('Clear filters') }}
                    </button>
                </div>
            </div>

            {{-- Grid + sidebar --}}
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div>
                    <div class="grid gap-5 sm:grid-cols-2 2xl:grid-cols-3">
                        <template x-for="asset in filteredAssets" :key="asset.id">
                            <article
                                class="group overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-white/5 dark:ring-white/10"
                                x-bind:class="selected.includes(asset.id) ? 'ring-2 ring-primary-500' : ''"
                            >
                                <div class="relative aspect-[16/10] overflow-hidden bg-gray-100 dark:bg-gray-900">
                                    <img
                                        x-bind:src="asset.thumbnail"
                                        x-bind:alt="asset.title"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                                    />
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/10"></div>
                                    <label class="absolute left-3 top-3 flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            x-bind:value="asset.id"
                                            x-model="selected"
                                            class="h-5 w-5 rounded border-white/70 bg-white/90 text-primary-600 shadow focus:ring-primary-500"
                                        />
                                        <span class="sr-only">{{ __('Select asset') }}</span>
                                    </label>
                                    <span
                                        class="absolute right-3 top-3 rounded-md bg-gray-950/80 px-2 py-1 text-xs font-bold text-white backdrop-blur"
                                        x-text="asset.format"
                                    ></span>
                                </div>
                                <div class="p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h3 class="truncate font-semibold text-gray-950 dark:text-white" x-text="asset.title"></h3>
                                            <p class="mt-1 line-clamp-2 text-sm text-gray-500 dark:text-gray-400" x-text="asset.description"></p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold"
                                            x-bind:class="statusClasses(asset.status_color)"
                                            x-text="asset.status"
                                        ></span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300" x-text="asset.campaign"></span>
                                        <template x-for="item in asset.platforms" :key="item">
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300" x-text="item"></span>
                                        </template>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="flex min-w-0 items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-m-photo" class="h-4 w-4 shrink-0" />
                                            <span class="truncate" x-text="asset.dimensions"></span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1.5">
                                            <button type="button" x-on:click="asset.download_url ? downloadSingle(asset) : null" x-bind:class="asset.download_url ? 'cursor-pointer hover:text-primary-600' : 'cursor-default'" class="flex items-center gap-1.5">
                                                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-4 w-4" />
                                                <span x-text="asset.downloads.toLocaleString()"></span>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="mt-4 grid grid-cols-[1fr_auto_auto] gap-2">
                                        <x-filament::button color="gray" size="sm" icon="heroicon-m-eye" x-on:click="openAsset(asset)">
                                            {{ __('Preview') }}
                                        </x-filament::button>
                                        <x-filament::icon-button
                                            color="gray"
                                            icon="heroicon-m-document-duplicate"
                                            :label="__('Copy promotional caption')"
                                            x-on:click="copyText(asset.caption, '{{ __('Caption copied') }}')"
                                        />
                                        <x-filament::icon-button
                                            color="gray"
                                            icon="heroicon-m-link"
                                            :label="__('Copy affiliate link')"
                                            x-on:click="copyText(asset.affiliate_url, '{{ __('Affiliate link copied') }}')"
                                        />
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div
                        x-show="filteredAssets.length === 0"
                        x-cloak
                        class="rounded-xl bg-white px-6 py-16 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                    >
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-photo" class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('No media found') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Try changing or clearing your filters.') }}</p>
                    </div>
                </div>

                {{-- Detail sidebar --}}
                <aside class="xl:sticky xl:top-6 xl:self-start">
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <template x-if="activeAsset">
                            <div>
                                <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                        {{ __('Selected promotional asset') }}
                                    </p>
                                    <h3 class="mt-1 font-bold text-gray-950 dark:text-white" x-text="activeAsset.title"></h3>
                                </div>
                                <div class="p-5">
                                    <div class="relative aspect-video overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-900">
                                        <img x-bind:src="activeAsset.thumbnail" x-bind:alt="activeAsset.title" class="h-full w-full object-cover" />
                                    </div>
                                    <div class="mt-5">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Recommended caption') }}</h4>
                                            <button type="button" x-on:click="copyText(activeAsset.caption, '{{ __('Caption copied') }}')" class="text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Copy') }}</button>
                                        </div>
                                        <div class="mt-2 rounded-lg bg-gray-50 p-3 text-sm leading-6 text-gray-700 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                            <p x-text="activeAsset.caption"></p>
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <div class="flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Your affiliate link') }}</h4>
                                            <button type="button" x-on:click="copyText(activeAsset.affiliate_url, '{{ __('Affiliate link copied') }}')" class="text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Copy') }}</button>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2 rounded-lg bg-gray-50 p-3 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                            <x-filament::icon icon="heroicon-m-link" class="h-4 w-4 shrink-0 text-gray-400" />
                                            <p class="min-w-0 truncate text-xs text-gray-600 dark:text-gray-300" x-text="activeAsset.affiliate_url"></p>
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Recommended platforms') }}</h4>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <template x-for="item in activeAsset.platforms" :key="item">
                                                <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200" x-text="item"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="mt-6 grid gap-2">
                                        <x-filament::button tag="a" x-bind:href="activeAsset.download_url || '#'" x-bind:disabled="!activeAsset.download_url" icon="heroicon-m-arrow-down-tray" class="w-full">
                                            {{ __('Download Asset') }}
                                        </x-filament::button>
                                        <x-filament::button color="gray" icon="heroicon-m-document-duplicate" class="w-full" x-on:click="copyPromoPackage(activeAsset)">
                                            {{ __('Copy Promo Package') }}
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </aside>
            </div>

            {{-- Toast --}}
            <div
                x-show="toast"
                x-transition
                x-cloak
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl bg-gray-950 px-4 py-3 text-sm font-medium text-white shadow-xl dark:bg-white dark:text-gray-950"
            >
                <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-success-400" />
                <span x-text="toast"></span>
            </div>
        </div>
    @endif

    @once
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('affiliateMediaLibrary', ({ assets, defaultAffiliateUrl }) => ({
                    assets,
                    defaultAffiliateUrl,
                    search: '',
                    category: '',
                    campaign: '',
                    format: '',
                    platform: '',
                    program: '',
                    selected: [],
                    activeAsset: assets[0] ?? null,
                    toast: '',
                    toastTimer: null,

                    get filteredAssets() {
                        const search = this.search.trim().toLowerCase();

                        return this.assets.filter((asset) => {
                            return (!search || asset.search.includes(search))
                                && (!this.category || asset.category === this.category)
                                && (!this.campaign || asset.campaign === this.campaign)
                                && (!this.format || asset.format === this.format)
                                && (!this.platform || asset.platforms.includes(this.platform))
                                && (!this.program || asset.program === this.program);
                        });
                    },

                    get mostDownloaded() {
                        return [...this.assets].sort((a, b) => b.downloads - a.downloads)[0] ?? null;
                    },

                    get newAssetsCount() {
                        return this.assets.filter((asset) => asset.status === 'New').length;
                    },

                    get campaignCount() {
                        return new Set(this.assets.map((asset) => asset.campaign)).size;
                    },

                    get hasFilters() {
                        return Boolean(this.search || this.category || this.campaign || this.format || this.platform || this.program);
                    },

                    openAsset(asset) {
                        this.activeAsset = asset;
                        if (window.innerWidth < 1280) {
                            this.$nextTick(() => {
                                this.$root.querySelector('aside')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            });
                        }
                    },

                    resetFilters() {
                        this.search = '';
                        this.category = '';
                        this.campaign = '';
                        this.format = '';
                        this.platform = '';
                        this.program = '';
                    },

                    async copyText(text, message = 'Copied') {
                        try {
                            await navigator.clipboard.writeText(text);
                            this.showToast(message);
                        } catch (error) {
                            const textarea = document.createElement('textarea');
                            textarea.value = text;
                            textarea.style.position = 'fixed';
                            textarea.style.opacity = '0';
                            document.body.appendChild(textarea);
                            textarea.select();
                            document.execCommand('copy');
                            textarea.remove();
                            this.showToast(message);
                        }
                    },

                    copyPromoPackage(asset) {
                        this.copyText(`${asset.caption}\n\n${asset.affiliate_url}`, '{{ __('Promo caption and link copied') }}');
                    },

                    downloadSelected() {
                        this.selected
                            .map((id) => this.assets.find((asset) => asset.id === id))
                            .filter(Boolean)
                            .forEach((asset, index) => {
                                if (!asset.download_url) return;
                                window.setTimeout(() => {
                                    const link = document.createElement('a');
                                    link.href = asset.download_url;
                                    link.download = '';
                                    link.click();
                                }, index * 250);
                            });
                        const count = this.selected.length;
                        this.showToast(count === 1 ? '1 asset prepared for download' : `${count} assets prepared for download`);
                    },

                    downloadSingle(asset) {
                        if (!asset.download_url) return;
                        const link = document.createElement('a');
                        link.href = asset.download_url;
                        link.download = '';
                        link.click();
                        this.showToast(`Downloading ${asset.title}`);
                    },

                    statusClasses(color) {
                        return {
                            success: 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
                            info: 'bg-info-50 text-info-700 dark:bg-info-400/10 dark:text-info-400',
                            warning: 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
                            danger: 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400',
                        }[color] ?? 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
                    },

                    showToast(message) {
                        window.clearTimeout(this.toastTimer);
                        this.toast = message;
                        this.toastTimer = window.setTimeout(() => { this.toast = ''; }, 2500);
                    },
                }));
            });
        </script>
    @endonce
</x-filament-panels::page>
