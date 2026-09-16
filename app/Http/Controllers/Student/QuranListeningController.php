<?php

namespace App\Http\Controllers\Student;

use App\Models\QuranListeningPlan;
use App\Models\QuranListeningPlanItem;
use App\Services\QuranAudioService;
use App\Services\QuranListeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «خطة الاستماع» في بوابة الطالب: يستمع للأجزاء المفتوحة، يسجّل استماعه،
 * ويتابع نتائج اختباراته — كل الوصول مقيّد بخطط الطالب نفسه.
 */
class QuranListeningController extends BaseStudentController
{
    public function __construct(
        private readonly QuranListeningService $listening,
        private readonly QuranAudioService $audio,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        // دُمجت «خطة الاستماع» في «ملفي القرآني».
        return redirect()->route('student.quran-profile');
    }

    public function show(Request $request, QuranListeningPlan $plan): View
    {
        $student = $this->currentStudent($request);
        $this->assertOwnsPlan($plan, $student);

        $plan->load([
            'teacher:id,name',
            'studySession:id,name',
            'khamsaReview:id,student_id,status',
            'items',
            'items.passedBy:id,name',
            'items.khamsaReviewItem',
            'items.listeningSession:id,date,from_page,to_page',
            'tests.items',
            'tests.testedBy:id,name',
        ]);

        return view('student.quran-listening.show', [
            'student' => $student,
            'plan' => $plan,
            'memorizedJuz' => $this->listening->memorizedJuzNumbers($student),
            'reciters' => $this->audio->reciters(),
        ]);
    }

    public function listen(Request $request, QuranListeningPlanItem $item): RedirectResponse
    {
        $student = $this->currentStudent($request);
        $this->assertOwnsItemPlan($item, $student);

        $this->listening->markListened($item, $request->user());

        return back()->with('success', 'تم تسجيل الاستماع للجزء '.$item->juz.' — بانتظار الاختبار');
    }

    public function audio(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
        $student = $this->currentStudent($request);
        $this->assertOwnsItemPlan($item, $student);

        abort_if($item->isLocked(), 403, 'هذا الجزء مقفل — يُفتح بعد نجاح الأجزاء السابقة');

        return response()->json($this->audio->playlistForItem($item, $request->query('reciter')));
    }

    public function progress(Request $request, QuranListeningPlanItem $item): JsonResponse
    {
        $student = $this->currentStudent($request);
        $this->assertOwnsItemPlan($item, $student);

        $data = $request->validate(['seconds' => ['required', 'integer', 'min:0', 'max:3600']]);

        $this->listening->recordProgress($item, (int) $data['seconds']);

        return response()->json(['ok' => true]);
    }

    private function assertOwnsPlan(QuranListeningPlan $plan, $student): void
    {
        abort_unless(
            (string) $plan->student_id === (string) $student->id,
            403,
            'لا تملك صلاحية الوصول لهذه الخطة'
        );
    }

    private function assertOwnsItemPlan(QuranListeningPlanItem $item, $student): void
    {
        $plan = $item->plan;

        abort_unless(
            $plan && (string) $plan->student_id === (string) $student->id,
            403,
            'هذا الجزء ليس ضمن خططك'
        );
    }
}
