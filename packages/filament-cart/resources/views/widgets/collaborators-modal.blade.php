<div class="space-y-4">
    @if(empty($collaborators))
        <div class="text-center py-8 text-gray-500">
            <x-heroicon-o-user-group class="w-12 h-12 mx-auto mb-2 text-gray-400" />
            <p>No collaborators found for this cart.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Email
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Role
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Joined
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($collaborators as $collaborator)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                {{ $collaborator['email'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span @class([
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200' => $collaborator['role'] === 'owner',
                                    'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' => $collaborator['role'] === 'editor',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' => $collaborator['role'] === 'viewer',
                                ])>
                                    {{ ucfirst($collaborator['role']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($collaborator['joined_at'])
                                    {{ \Carbon\Carbon::parse($collaborator['joined_at'])->diffForHumans() }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 pt-2">
            <span>Cart ID: {{ $cart->identifier }}</span>
            <span>Total Collaborators: {{ count($collaborators) }}</span>
        </div>
    @endif
</div>
