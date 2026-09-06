<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\FaithMeetingAttendanceStatus;
use App\Enums\FaithMeetingNoteType;
use App\Models\FaithMeeting;
use App\Models\FaithMeetingNote;
use App\Models\FaithMeetingStudent;
use App\Services\AuditLogger;
use App\Services\QuranScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * اللقاءات الإيمانية الخاصة بالمعلم (كمنظم/مشرف فقط) — يسجل الحضور
 * والملاحظات ويُنجز الإجراءات الموكلة إليه.
 */
class FaithMeetingController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $meetings = $this->scope->meetingsQuery($teacher)
            ->with(['supervisor:id,name', 'teacher:id,name'])
            ->withCount('studentAttendances')
            ->when($request->input('filter', 'upcoming') === 'upcoming',
                fn ($q) => $q->where('status', 'scheduled')->where('date', '>=', now()->toDateString()))
            ->when($request->input('filter') === 'past',
                fn ($q) => $q->where(fn ($q2) => $q2->where('status', 'completed')
                    ->orWhere(fn ($q3) => $q3->where('status', 'scheduled')->where('date', '<', now()->toDateString()))))
            ->when($request->input('filter') === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when($request->input('filter') === 'all', fn ($q) => $q)
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.quran.faith-meetings.index', [
            'meetings' => $meetings,
            'filter' => $request->input('filter', 'upcoming'),
        ]);
    }

    public function show(Request $request, FaithMeeting $meeting): View
    {
        $teacher = $this->currentTeacher($request);
        $this->assertManages($teacher, $meeting);

        $meeting->load([
            'supervisor:id,name',
            'teacher:id,name',
            'studentAttendances.student:id,name,classroom_id',
            'studentAttendances.student.classroom:id,name',
            'notes.student:id,name',
            'notes.createdBy:id,name',
            'notes.assignedTo:id,name',
        ]);

        return view('teacher.quran.faith-meetings.show', [
            'meeting' => $meeting,
            'attendanceStatuses' => FaithMeetingAttendanceStatus::cases(),
            'noteTypes' => FaithMeetingNoteType::cases(),
        ]);
    }

    public function attendance(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertManages($teacher, $meeting);

        $data = $request->validate([
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', Rule::in(['attended', 'absent', 'excused'])],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($meeting, $data) {
            foreach ($data['statuses'] as $studentId => $status) {
                FaithMeetingStudent::query()
                    ->where('meeting_id', $meeting->id)
                    ->where('student_id', $studentId)
                    ->update([
                        'attendance_status' => $status,
                        'note' => $data['notes'][$studentId] ?? null,
                    ]);
            }
        });

        $this->audit->log(
            'faith_meeting.attendance_changed',
            'faith_meeting',
            $meeting->id,
            $meeting->tenant_id,
            after: ['meeting_id' => $meeting->id, 'statuses' => $data['statuses']],
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ الحضور بنجاح');
    }

    public function storeNote(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertManages($teacher, $meeting);

        $data = $request->validate([
            'note_type' => ['required', Rule::in(['note', 'suggestion', 'action_item'])],
            'content' => ['required', 'string', 'max:5000'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $note = FaithMeetingNote::create([
            'meeting_id' => $meeting->id,
            'student_id' => $data['student_id'] ?? null,
            'note_type' => $data['note_type'],
            'content' => $data['content'],
            'created_by' => $request->user()->id,
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['note_type'] === FaithMeetingNoteType::ActionItem->value ? 'pending' : null,
        ]);

        $this->audit->logModel('faith_meeting.note_added', $note, actor: $request->user());

        return back()->with('success', 'تمت إضافة '.$note->note_type->label());
    }

    public function completeNote(Request $request, FaithMeetingNote $note): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertManages($teacher, $note->meeting);

        if ($note->status?->value !== 'pending') {
            abort(422);
        }

        $before = $note->getAttributes();
        $note->update(['status' => 'completed']);
        $this->audit->logModel('faith_meeting.note_updated', $note, $before, actor: $request->user());

        return back()->with('success', 'تم إنجاز الإجراء');
    }

    /** إغلاق اللقاء (إنهاؤه) من قبل المشرف. */
    public function complete(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertManages($teacher, $meeting);

        $before = $meeting->getAttributes();
        $meeting->update(['status' => 'completed']);
        $this->audit->logModel('faith_meeting.updated', $meeting, $before, actor: $request->user());

        return back()->with('success', 'تم إنهاء اللقاء وحفظ سجله');
    }

    private function assertManages($teacher, FaithMeeting $meeting): void
    {
        if ($meeting->supervisor_id !== $teacher->id && $meeting->teacher_id !== $teacher->id) {
            abort(403, 'لا تملك صلاحية الوصول لهذا اللقاء');
        }
    }
}
