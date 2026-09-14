<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Unvalidated Addresses
        </x-slot>
        <x-slot name="description">
            Review and validate customer addresses individually, or use the header action to validate up to 100 at once.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 font-medium">Customer</th>
                        <th class="px-3 py-2 font-medium">Address</th>
                        <th class="px-3 py-2 font-medium">Country</th>
                        <th class="px-3 py-2 text-right font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->getUnvalidatedAddresses() as $address)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $address['customer_name'] }}</td>
                            <td class="px-3 py-2">{{ $address['full_address'] }}</td>
                            <td class="px-3 py-2">{{ $address['country'] }}</td>
                            <td class="px-3 py-2 text-right">
                                <x-filament::button
                                    size="sm"
                                    color="success"
                                    wire:click="validateAddress('{{ $address['id'] }}')"
                                >
                                    Validate
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No unvalidated addresses in the current scope.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
