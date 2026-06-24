<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Livewire;

use AIArmada\Communications\Models\NotificationInbox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class InboxIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    /** @return LengthAwarePaginator<int, NotificationInbox> */
    #[Computed]
    public function inboxItems(): LengthAwarePaginator
    {
        $query = NotificationInbox::query()->recent();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'archived') {
            $query->archived();
        }

        return $query->paginate(15);
    }

    public function markAsRead(string $id): void
    {
        NotificationInbox::query()
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        NotificationInbox::query()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function archive(string $id): void
    {
        NotificationInbox::query()
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function render(): View
    {
        return view('communications::livewire.inbox-index');
    }
}
