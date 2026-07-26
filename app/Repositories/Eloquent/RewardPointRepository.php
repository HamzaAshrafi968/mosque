<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RewardPointRepositoryInterface;
use App\Models\RewardPoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RewardPointRepository extends BaseRepository implements RewardPointRepositoryInterface
{
    public function __construct(RewardPoint $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['student:id,name', 'awardedBy:id,name', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->when(! empty($filters['student_id']), fn ($q) => $q->where('student_id', $filters['student_id']))
            ->when(! empty($filters['awarded_by']), fn ($q) => $q->where('awarded_by', $filters['awarded_by']))
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
