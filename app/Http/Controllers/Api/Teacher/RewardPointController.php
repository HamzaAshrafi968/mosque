<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\RewardPoint;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardPointController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $points = RewardPoint::query()
            ->with(['student:id,name', 'awardedBy:id,name', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->where('awarded_by', $request->user()->id)
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($points);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:earned,deducted'],
            'notes' => ['nullable', 'string'],
        ]);

        $rewardPoint = RewardPoint::create([
            ...$data,
            'awarded_by' => $request->user()->id,
        ]);

        return response()->json($rewardPoint, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $point = RewardPoint::findOrFail($id);
        $point->delete();

        return response()->json(null, 204);
    }
}
