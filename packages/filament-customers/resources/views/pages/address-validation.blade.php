<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Invalid Addresses
        </x-slot>
        <x-slot name="description">
            Customer addresses that currently fail their country validation profile. Fix the address data to clear an entry; nothing is stored by this page.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 font-medium">Customer</th>
                        <th class="px-3 py-2 font-medium">Address</th>
                        <th class="px-3 py-2 font-medium">Country</th>
                        <th class="px-3 py-2 font-medium">Violations</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($this->getInvalidAddresses() as $address)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $address['customer_name'] }}</td>
                            <td class="px-3 py-2">{{ $address['full_address'] }}</td>
                            <td class="px-3 py-2">{{ $address['country'] }}</td>
                            <td class="px-3 py-2">
                                <ul class="list-disc pl-4">
                                    @foreach ($address['violations'] as $violation)
                                        <li>{{ $violation }}</li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No invalid addresses in the current scope.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
