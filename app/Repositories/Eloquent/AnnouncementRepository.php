<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AnnouncementRepositoryInterface;
use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementRepository extends BaseRepository implements AnnouncementRepositoryInterface
{
    public function __construct(Announcement $model)
    {
        parent::__construct($model);
    }

    public function paginateWithAuthor(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['author:id,name', 'classroom:id,name'])
            ->latest('published_at')
            ->paginate($perPage);
    }

    public function latest(int $limit = 5): Collection
    {
        return $this->model
            ->with('author:id,name')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}
