<?php

namespace App\Actions\Teacher\Grade;

use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Models\Exam;
use App\Models\Student;
use App\Services\DashboardService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveGradesAction
{
    public function __construct(private readonly GradeRepositoryInterface $repository) {}

    public function execute(array $data, Exam $exam): string
    {
        if ($exam->grades()->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages([
                'scores' => ['لا يمكن تعديل درجات هذا الامتحان بعد اعتمادها'],
            ]);
        }

        $scores = collect($data['scores']);

        $allowedIds = Student::query()
            ->active()
            ->where('classroom_id', $exam->classroom_id)
            ->when($exam->section_id, fn ($q) => $q->where('section_id', $exam->section_id))
            ->pluck('id');

        $invalid = $scores->keys()
            ->filter(fn ($id) => ! $allowedIds->contains($id))
            ->values();

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scores' => ['يحتوي الطلب على طلاب غير مسجلين في صف/شعبة هذا الامتحان'],
            ]);
        }

        $overMax = $scores
            ->filter(fn ($score) => $score !== null && $score !== '' && (float) $score > (float) $exam->total_marks);

        if ($overMax->isNotEmpty()) {
            throw ValidationException::withMessages([
                'scores' => ['الدرجة لا يمكن أن تتجاوز الحد الأقصى للامتحان ('.$exam->total_marks.')'],
            ]);
        }

        $status = $data['action'] === 'submit' ? 'submitted' : 'draft';
        $now = now();

        $rows = $scores
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

        $this->repository->upsertBatch($rows);

        DashboardService::flush($exam->tenant_id);

        return $status;
    }
}
