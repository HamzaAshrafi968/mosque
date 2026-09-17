<?php

namespace App\Http\Controllers\Student;

use App\Models\RewardPoint;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «نقاطي»: بوابة الطالب — الرصيد وسجل النقاط (مكافآت تلقائية من الحفظ
 * والخمسات والاختبارات، أو نقاط يمنحها الأستاذ يدوياً).
 */
class RewardPointController extends BaseStudentController
{
    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        $points = RewardPoint::query()
            ->with('studySession:id,name')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $totals = RewardPoint::query()
            ->where('student_id', $student->id)
            ->selectRaw("
                SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN type = 'deducted' THEN points ELSE 0 END) as total_deducted
            ")
            ->first();

        return view('student.reward-points', [
            'student' => $student,
            'points' => $points,
            'balance' => $student->totalPoints(),
            'totalEarned' => $totals->total_earned ?? 0,
            'totalDeducted' => $totals->total_deducted ?? 0,
        ]);
    }
}
