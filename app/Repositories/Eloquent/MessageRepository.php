<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageRepository extends BaseRepository implements MessageRepositoryInterface
{
    public function __construct(Message $model)
    {
        parent::__construct($model);
    }

    public function paginateByUser(string $userId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['sender:id,name', 'recipient:id,name'])
            ->where(fn ($q) => $q->where('recipient_id', $userId)->orWhere('sender_id', $userId))
            ->latest()
            ->paginate($perPage);
    }

    public function markRead(string $userId): void
    {
        $this->model->where('recipient_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
