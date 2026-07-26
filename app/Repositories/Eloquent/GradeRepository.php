<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Models\Grade;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

class GradeRepository extends BaseRepository implements GradeRepositoryInterface
{
    public function __construct(Grade $model)
    {
        parent::__construct($model);
    }

    public function upsertBatch(array $rows): void
    {
        Grade::upsert(
            $rows,
            ['exam_id', 'student_id'],
            ['score', 'status', 'updated_at']
        );
    }

    public function getByExam(string $examId): Collection
    {
        return $this->model
            ->with('student:id,name')
            ->where('exam_id', $examId)
            ->orderByDesc('score')
            ->get();
    }

    public function approveByExam(string $examId): void
    {
        $this->model
            ->where('exam_id', $examId)
            ->where('status', 'submitted')
            ->update(['status' => 'approved']);
    }

    public function paginateApproved(int $perPage = 100): CursorPaginator
    {
        return $this->model
            ->with(['student:id,name', 'exam:id,title,total_marks,subject_id', 'exam.subject:id,name'])
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }
}
