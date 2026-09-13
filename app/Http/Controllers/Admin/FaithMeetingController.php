<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaithMeetingAttendanceStatus;
use App\Enums\FaithMeetingNoteType;
use App\Enums\FaithMeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\FaithMeeting;
use App\Models\FaithMeetingNote;
use App\Models\FaithMeetingStudent;
use App\Models\FaithMeetingTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FaithMeetingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'upcoming');
        $today = now()->toDateString();

        $meetings = FaithMeeting::query()
            ->with(['supervisor:id,name', 'teacher:id,name'])
            ->withCount(['studentAttendances'])
            ->when($filter === 'upcoming',
                fn ($q) => $q->where('status', FaithMeetingStatus::Scheduled)->where('date', '>=', $today))
            ->when($filter === 'past',
                fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('status', FaithMeetingStatus::Completed)
                    ->orWhere(fn ($q3) => $q3->where('status', FaithMeetingStatus::Scheduled)->where('date', '<', $today))))
            ->when($filter === 'cancelled', fn ($q) => $q->where('status', FaithMeetingStatus::Cancelled))
            ->when($filter === 'all', fn ($q) => $q)
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        $attendanceTaken = function (FaithMeeting $meeting): bool {
            return $meeting->studentAttendances()->whereNotNull('attendance_status')->exists();
        };

        return view('admin.faith-meetings.index', [
            'meetings' => $meetings,
            'filter' => $filter,
            'attendanceTaken' => $attendanceTaken,
            'statuses' => FaithMeetingStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $preset = null;
        if ($templateId = $request->input('template')) {
            $preset = FaithMeetingTemplate::find($templateId);
        }

        return view('admin.faith-meetings.create', [
            'students' => Student::query()->active()->with('classroom:id,name')->orderBy('name')->get(['id', 'name', 'classroom_id']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'preset' => $preset,
            'statuses' => FaithMeetingStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->meetingValidated($request);

        $meeting = DB::transaction(function () use ($request, $data) {
            $meeting = FaithMeeting::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'date' => $data['date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'teacher_id' => $data['teacher_id'] ?? null,
                'location' => $data['location'] ?? null,
                'general_notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? FaithMeetingStatus::Scheduled,
                'created_by' => $request->user()->id,
            ]);

            $this->syncStudents($meeting, $data['student_ids'] ?? [], $request->user());

            return $meeting;
        });

        $this->audit->logModel('faith_meeting.created', $meeting, actor: $request->user());

        return redirect()
            ->route('admin.faith-meetings.show', $meeting)
            ->with('success', 'تم إنشاء اللقاء الإيماني');
    }

    public function show(FaithMeeting $meeting): View
    {
        $meeting->load([
            'supervisor:id,name',
            'teacher:id,name',
            'studentAttendances.student:id,name,classroom_id',
            'studentAttendances.student.classroom:id,name',
            'notes.student:id,name',
            'notes.createdBy:id,name',
            'notes.assignedTo:id,name',
        ]);

        return view('admin.faith-meetings.show', [
            'meeting' => $meeting,
            'attendanceStatuses' => FaithMeetingAttendanceStatus::cases(),
            'noteTypes' => FaithMeetingNoteType::cases(),
            'statuses' => FaithMeetingStatus::cases(),
            'staffUsers' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEACHER])
                ->orderBy('name')
                ->get(['id', 'name']),
            'students' => Student::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(FaithMeeting $meeting): View
    {
        $meeting->load('studentAttendances');

        return view('admin.faith-meetings.edit', [
            'meeting' => $meeting,
            'students' => Student::query()->active()->with('classroom:id,name')->orderBy('name')->get(['id', 'name', 'classroom_id']),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => FaithMeetingStatus::cases(),
        ]);
    }

    public function update(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $data = $this->meetingValidated($request);
        $before = $meeting->getAttributes();

        DB::transaction(function () use ($request, $meeting, $data) {
            $meeting->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'date' => $data['date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'teacher_id' => $data['teacher_id'] ?? null,
                'location' => $data['location'] ?? null,
                'general_notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? FaithMeetingStatus::Scheduled,
            ]);

            $this->syncStudents($meeting, $data['student_ids'] ?? [], $request->user());
        });

        $this->audit->logModel('faith_meeting.updated', $meeting, $before, actor: $request->user());

        return redirect()
            ->route('admin.faith-meetings.show', $meeting)
            ->with('success', 'تم تحديث اللقاء');
    }

    public function status(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
        ]);

        $before = $meeting->getAttributes();
        $meeting->update(['status' => $data['status']]);
        $this->audit->logModel('faith_meeting.updated', $meeting, $before, actor: $request->user());

        return back()->with('success', 'تم تحديث حالة اللقاء');
    }

    public function destroy(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $this->audit->logModel('faith_meeting.deleted', $meeting, actor: $request->user());
        $meeting->delete();

        return redirect()->route('admin.faith-meetings.index')->with('success', 'تم حذف اللقاء');
    }

    /** حفظ حضور الطلاب المحددين للقاء. */
    public function attendance(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $data = $request->validate([
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['nullable', Rule::in(['attended', 'absent', 'excused'])],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $statuses = collect($data['statuses'])
            ->filter(fn ($status) => $status !== null && $status !== '')
            ->all();

        if ($statuses === []) {
            throw ValidationException::withMessages(['statuses' => 'حدد حالة طالب واحد على الأقل']);
        }

        $attached = $meeting->studentAttendances()->pluck('student_id');

        DB::transaction(function () use ($meeting, $data, $statuses) {
            foreach ($statuses as $studentId => $status) {
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
            after: ['meeting_id' => $meeting->id, 'students' => $attached->count(), 'statuses' => $statuses],
            actor: $request->user()
        );

        return back()->with('success', 'تم حفظ الحضور بنجاح');
    }

    /** إضافة ملاحظة عامة/خاصة بطالب أو اقتراح أو إجراء. */
    public function storeNote(Request $request, FaithMeeting $meeting): RedirectResponse
    {
        $data = $this->noteValidated($request);

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
        if ($note->status?->value !== 'pending') {
            abort(422);
        }

        $before = $note->getAttributes();
        $note->update(['status' => 'completed']);
        $this->audit->logModel('faith_meeting.note_updated', $note, $before, actor: $request->user());

        return back()->with('success', 'تم إنجاز الإجراء');
    }

    public function destroyNote(Request $request, FaithMeetingNote $note): RedirectResponse
    {
        $this->audit->logModel('faith_meeting.note_deleted', $note, actor: $request->user());
        $note->delete();

        return back()->with('success', 'تم حذف الملاحظة');
    }

    private function syncStudents(FaithMeeting $meeting, array $studentIds, $actor): void
    {
        $current = $meeting->studentAttendances()->pluck('student_id');
        $next = collect($studentIds)->filter()->unique()->values();

        $added = $next->diff($current);
        $removed = $current->diff($next);

        if ($added->isNotEmpty()) {
            $rows = $added->map(fn ($id) => [
                'id' => (string) Str::uuid(),
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'student_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            FaithMeetingStudent::insert($rows);
        }

        if ($removed->isNotEmpty()) {
            FaithMeetingStudent::query()
                ->where('meeting_id', $meeting->id)
                ->whereIn('student_id', $removed)
                ->delete();
        }

        if ($added->isNotEmpty() || $removed->isNotEmpty()) {
            $this->audit->log(
                'faith_meeting.students_changed',
                'faith_meeting',
                $meeting->id,
                $meeting->tenant_id,
                before: ['students' => $current->all()],
                after: ['students' => $next->all(), 'added' => $added->all(), 'removed' => $removed->all()],
                actor: $actor
            );
        }
    }

    private function meetingValidated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'supervisor_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'teacher_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
        ]);
    }

    private function noteValidated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'note_type' => ['required', Rule::in(['note', 'suggestion', 'action_item'])],
            'content' => ['required', 'string', 'max:5000'],
            'student_id' => ['nullable', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
