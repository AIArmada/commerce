<div wire:poll.60s>
    <div class="flex gap-2 mb-4">
        <button
            wire:click="setFilter('all')"
            class="{{ $filter === 'all' ? 'font-bold underline' : '' }}"
        >All</button>
        <button
            wire:click="setFilter('unread')"
            class="{{ $filter === 'unread' ? 'font-bold underline' : '' }}"
        >Unread</button>
        <button
            wire:click="setFilter('archived')"
            class="{{ $filter === 'archived' ? 'font-bold underline' : '' }}"
        >Archived</button>

        @if ($filter === 'unread' && $this->inboxItems->total() > 0)
            <button wire:click="markAllAsRead" class="ml-auto text-sm text-blue-600">
                Mark All as Read
            </button>
        @endif
    </div>

    @forelse ($this->inboxItems as $item)
        <div
            wire:key="inbox-{{ $item->id }}"
            class="flex items-start gap-3 p-3 border-b {{ $item->read_at === null ? 'bg-blue-50' : '' }}"
        >
            <span
                class="inline-block w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
                style="background-color: {{ match($item->priority->value) { 'urgent' => '#dc2626', 'high' => '#f59e0b', 'normal' => '#3b82f6', default => '#9ca3af' } }}"
                title="{{ $item->priority->label() }}"
            ></span>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">{{ $item->family->label() }}</span>
                    <span class="text-xs text-gray-400">{{ $item->created_at->diffForHumans() }}</span>
                </div>

                <p class="{{ $item->read_at === null ? 'font-semibold' : '' }} truncate">
                    {{ $item->title }}
                </p>

                @if ($item->body)
                    <p class="text-sm text-gray-600 truncate">{{ Str::limit($item->body, 120) }}</p>
                @endif
            </div>

            <div class="flex items-center gap-1 shrink-0">
                @if ($item->read_at === null)
                    <button wire:click="markAsRead('{{ $item->id }}')" class="text-xs text-blue-600 hover:underline" title="Mark as read">
                        Read
                    </button>
                @endif

                @if ($item->archived_at === null)
                    <button wire:click="archive('{{ $item->id }}')" class="text-xs text-gray-500 hover:underline" title="Archive">
                        Archive
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div class="p-6 text-center text-gray-500">
            No notifications found.
        </div>
    @endforelse

    @if ($this->inboxItems->hasPages())
        <div class="mt-4">
            {{ $this->inboxItems->links() }}
        </div>
    @endif
</div>
