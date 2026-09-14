<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Form Structure
            </x-slot>

            @php
                $summary = $this->getBuilderSummary();
            @endphp

            <div class="grid grid-cols-2 gap-4">
                <x-filament::section heading="Sections">
                    {{ $summary['sections'] }}
                </x-filament::section>

                <x-filament::section heading="Questions">
                    {{ $summary['questions'] }}
                </x-filament::section>
            </div>

            <div class="mt-4">
                <x-filament::link :href="$summary['edit_url']">
                    Edit this form
                </x-filament::link>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
