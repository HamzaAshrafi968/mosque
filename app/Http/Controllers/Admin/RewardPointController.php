<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPoint;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardPointController extends Controller
{
    public function index(Request $request): View
    {
        $points = RewardPoint::query()
            ->with(['student:id,name', 'awardedBy:id,name', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate(20);

        $students = Student::orderBy('name')->get(['id', 'name']);

        $totals = RewardPoint::query()
            ->selectRaw("
                SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN type = 'deducted' THEN points ELSE 0 END) as total_deducted
            ")
            ->first();

        return view('admin.reward-points.index', [
            'points' => $points,
            'students' => $students,
            'totalEarned' => $totals->total_earned ?? 0,
            'totalDeducted' => $totals->total_deducted ?? 0,
        ]);
    }
}
