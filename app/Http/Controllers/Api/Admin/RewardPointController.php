<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardPointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $points = RewardPoint::query()
            ->with(['student:id,name', 'awardedBy:id,name', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($points);
    }
}
