<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Merge Customers
        </x-slot>
        <x-slot name="description">
            Merge two customer records together. The source customer's data (addresses, notes)
            will be transferred to the target customer, and the source will be deleted.
        </x-slot>

        <x-filament::grid class="gap-4">
            <form wire:submit="merge">
                {{ $this->form }}

                <div class="mt-6 flex justify-end gap-3">
                    @foreach ($this->getFormActions() as $action)
                        {{ $action }}
                    @endforeach
                </div>
            </form>
        </x-filament::grid>
    </x-filament::section>
</x-filament-panels::page>
