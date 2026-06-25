<?php

declare(strict_types=1);

namespace AIArmada\Communications\Http\Livewire;

use AIArmada\Communications\Models\NotificationInbox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
        $query = $this->recipientQuery()->recent();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'archived') {
            $query->archived();
        }

        return $query->paginate(15);
    }

    public function markAsRead(string $id): void
    {
        $this->recipientQuery()
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        $this->recipientQuery()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function archive(string $id): void
    {
        $this->recipientQuery()
            ->where('id', $id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'unread', 'archived'], true)
            ? $filter
            : 'all';
        $this->resetPage();
    }

    public function render(): View
    {
        return view('communications::livewire.inbox-index');
    }

    /**
     * @return Builder<NotificationInbox>
     */
    private function recipientQuery(): Builder
    {
        $recipient = Auth::user();
        $query = NotificationInbox::query();

        if (! $recipient instanceof Model) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey());
    }
}
