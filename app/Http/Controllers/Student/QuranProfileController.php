<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuranKhamsaReviewStatus;
use App\Enums\QuranListeningItemStatus;
use App\Enums\QuranMemorizationBatchStatus;
use App\Models\QuranKhamsaReview;
use App\Models\QuranListeningPlan;
use App\Services\QuranAudioService;
use App\Services\QuranJourneyService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranSettingsService;
use App\Services\QuranTeacherTimelineService;
use App\Services\StudentAcademicService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «ملفي القرآني» — الشاشة الموحدة للطالب: الإنجاز، الدفعة الحالية ودورتها
 * (مراجعة 5 + خطة الاستماع والاختبار + الاستماع مع المعلم) في صفحة واحدة.
 * كل الوصول محصور في طالب الجلسة نفسه.
 */
class QuranProfileController extends BaseStudentController
{
    public function __construct(
        StudentAcademicService $academic,
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranSettingsService $settings,
        private readonly QuranJourneyService $journeys,
        private readonly QuranAudioService $audio,
        private readonly QuranTeacherTimelineService $timeline,
    ) {
        parent::__construct($academic);
    }

    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        $states = $this->gating->sync($student, $request->user());
        $currentState = null;

        foreach ($states as $state) {
            if ($state['status'] !== QuranMemorizationBatchStatus::Locked
                && $state['status'] !== QuranMemorizationBatchStatus::Passed) {
                $currentState = $state;

                break;
            }
        }

        $currentBatch = $currentState['batch'] ?? null;
        $currentBatch?->load(['plan', 'review5', 'retakeReview5', 'lastTest']);

        $plans = QuranListeningPlan::query()
            ->with([
                'student:id,name',
                'teacher:id,name',
                'studySession:id,name',
                'khamsaReview:id,student_id,status',
                'items',
                'items.passedBy:id,name',
                'items.khamsaReviewItem',
                'items.listeningSession:id,date,from_page,to_page',
                'tests.items',
                'tests.examiner:id,name',
            ])
            ->where('student_id', $student->id)
            ->orderByRaw("case status when 'active' then 0 when 'completed' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $reviews = QuranKhamsaReview::query()
            ->with([
                'student:id,name',
                'teacher:id,name',
                'studySession:id,name',
                'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
                'items.completedBy:id,name',
                'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
            ])
            ->where('student_id', $student->id)
            ->orderByRaw("case status when 'pending' then 0 when 'completed' then 1 else 2 end")
            ->orderByDesc('assigned_at')
            ->limit(10)
            ->get();

        $plan = $currentBatch?->plan;

        if (! $plan || ! $plan->isActive()) {
            $plan = $plans->first(fn (QuranListeningPlan $row) => $row->isActive()) ?? $plan;
        }

        $plan?->load([
            'student:id,name',
            'teacher:id,name',
            'studySession:id,name',
            'khamsaReview:id,student_id,status',
            'items',
            'items.passedBy:id,name',
            'items.khamsaReviewItem',
            'items.listeningSession:id,date,from_page,to_page',
            'tests.items',
            'tests.examiner:id,name',
        ]);

        $review = $currentBatch?->review5;

        if (! $review) {
            $review = $reviews->first(fn (QuranKhamsaReview $row) => $row->status === QuranKhamsaReviewStatus::Pending);
        }

        $review?->load([
            'student:id,name',
            'teacher:id,name',
            'studySession:id,name',
            'items.completedBy:id,name',
            'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
        ]);

        $retakeReview = $currentBatch?->retakeReview5;

        $retakeReview?->load([
            'student:id,name',
            'teacher:id,name',
            'studySession:id,name',
            'items.completedBy:id,name',
            'items.quranReviewSession:id,date,from_page,to_page,mastery_percentage',
        ]);

        $failedJuz = $currentBatch ? $this->gating->failedJuzNumbers($currentBatch) : [];

        $timeline = $this->timeline->forStudent($student);

        return view('student.quran-profile', [
            'student' => $student,
            'states' => $states,
            'currentState' => $currentState,
            'currentBatch' => $currentBatch,
            'plan' => $plan,
            'review' => $review,
            'retakeReview' => $retakeReview,
            'failedJuz' => $failedJuz,
            'listeningItems' => $plan ? $plan->items->where('status', QuranListeningItemStatus::Listened) : collect(),
            'reciters' => $this->audio->reciters(),
            'memorizedJuz' => $this->gating->memorizedJuzNumbers($student),
            'plans' => $plans,
            'reviews' => $reviews,
            'timeline' => $timeline,
            'memorizationProgress' => $currentBatch ? $this->gating->batchMemorizationProgress($currentBatch) : null,
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
            'journey' => $this->journeys->journey($student),
        ]);
    }
}
