<?php

namespace App\Actions\Teacher\Grade;

use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Models\Exam;
use Illuminate\Support\Str;

class SaveGradesAction
{
    public function execute(GradeRepositoryInterface $repository, array $data, Exam $exam): string
    {
        $status = $data['action'] === 'submit' ? 'submitted' : 'draft';
        $now = now();

        $rows = collect($data['scores'])
            ->filter(fn ($score) => $score !== null && $score !== '')
            ->map(fn ($score, $studentId) => [
                'id' => (string) Str::uuid(),
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
                'student_id' => $studentId,
                'score' => $score,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();

        $repository->upsertBatch($rows);

        return $status;
    }
}
