<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\ParentStudent;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\AudioUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()
            ->with(['author:id,name', 'classroom:id,name', 'studySession:id,name,gender'])
            ->latest('published_at')
            ->paginate(15);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            // كل الصفوف بكل الدوامات حتى يفلترها النموذج حسب الدوام المستهدف.
            'classrooms' => Classroom::query()
                ->withoutGlobalScope('study_session')
                ->orderBy('name')
                ->get(['id', 'name', 'study_session_id']),
            'studySessions' => StudySession::query()->orderForDisplay()->get(['id', 'name', 'gender']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->tenantId($request);
        $sessionId = $request->input('study_session_id');

        // الصف يجب أن يخص الدوام المستهدف أو يكون صفًا مشتركًا (بلا دوام).
        $classroomRule = Rule::exists('classrooms', 'id')->where(function ($query) use ($tenantId, $sessionId) {
            $query->where('tenant_id', $tenantId);

            if (filled($sessionId)) {
                $query->where(function ($sub) use ($sessionId) {
                    $sub->whereNull('study_session_id')->orWhere('study_session_id', $sessionId);
                });
            }
        });

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'required_without:audio'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom,classrooms'],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', $classroomRule],
            'audio' => AudioUpload::rules(),
            'auto_delete' => ['nullable', 'boolean'],
        ]);

        $audio = $request->file('audio');

        $announcement = Announcement::create([
            ...Arr::except($data, ['audio', 'auto_delete']),
            'user_id' => $request->user()->id,
            'published_at' => now(),
            'audio_path' => $audio ? AudioUpload::store($audio) : null,
            'audio_original_name' => $audio?->getClientOriginalName(),
            // Audio announcements disappear automatically one week after
            // publishing unless the manager opted out of the auto-delete.
            'expires_at' => ($audio && $request->boolean('auto_delete', true))
                ? now()->addWeek()
                : null,
        ]);

        $this->notifyAudience($request, $announcement);

        return back()->with('success', $audio ? 'تم نشر الإعلان الصوتي' : 'تم نشر الإعلان');
    }

    /** Fan-out to the matching audience (spec §39: new announcement). */
    private function notifyAudience(Request $request, Announcement $announcement): void
    {
        $tenantId = $this->tenantId($request);
        $notifier = app(NotificationService::class);
        $sessionId = $announcement->study_session_id;
        $title = 'إعلان جديد';
        $body = "«{$announcement->title}»";

        if (filled($announcement->body)) {
            $body .= ' — '.mb_substr((string) $announcement->body, 0, 150);
        } elseif ($announcement->hasAudio()) {
            $body .= ' — إعلان صوتي';
        }

        $userIds = match ($announcement->audience) {
            'all' => $this->allAudienceUserIds($tenantId, $sessionId, $notifier),

            'teachers' => $this->teacherUserIds($tenantId, $sessionId),

            'guardians' => $this->guardianUserIds($tenantId, $sessionId),

            'classroom' => $notifier->recipientsForStudents(
                $this->shiftRoster($tenantId, $sessionId)
                    ->where('students.classroom_id', $announcement->classroom_id)
                    ->get(['id', 'tenant_id', 'user_id'])
            ),

            'classrooms' => $notifier->recipientsForStudents(
                $this->shiftRoster($tenantId, $sessionId)->get(['id', 'tenant_id', 'user_id'])
            ),

            default => collect(),
        };

        $notifier->send($userIds, $title, $body);
    }

    /** الجميع: كل الجامع، أو كل من يخص الدوام المستهدف + إدارة الجامع. */
    private function allAudienceUserIds(string $tenantId, ?string $sessionId, NotificationService $notifier): Collection
    {
        if (! $sessionId) {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->pluck('id');
        }

        return $notifier->recipientsForStudents(
            $this->shiftRoster($tenantId, $sessionId)->get(['id', 'tenant_id', 'user_id'])
        )
            ->merge($this->teacherUserIds($tenantId, $sessionId))
            ->merge(
                User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('role', User::ROLE_ADMIN)
                    ->pluck('id')
            )
            ->unique()
            ->values();
    }

    /** حسابات المعلمين المنتمين للدوام (الأساسي أو الجدول الوسيط). */
    private function teacherUserIds(string $tenantId, ?string $sessionId): Collection
    {
        if (! $sessionId) {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->where('role', User::ROLE_TEACHER)
                ->pluck('id');
        }

        return Teacher::query()
            ->withoutGlobalScope('study_session')
            ->where('teachers.tenant_id', $tenantId)
            ->whereNotNull('teachers.user_id')
            ->when($sessionId, fn (Builder $query) => $query->where(function (Builder $sub) use ($sessionId) {
                $sub->where('teachers.study_session_id', $sessionId)
                    ->orWhereHas('studySessions', fn (Builder $relation) => $relation->whereKey($sessionId));
            }))
            ->pluck('teachers.user_id');
    }

    /** حسابات أولياء أمور طلاب الدوام (أو كل أولياء الجامع بلا دوام). */
    private function guardianUserIds(string $tenantId, ?string $sessionId): Collection
    {
        if (! $sessionId) {
            return User::query()
                ->where('tenant_id', $tenantId)
                ->where('role', User::ROLE_GUARDIAN)
                ->pluck('id');
        }

        return ParentStudent::query()
            ->whereIn('student_id', $this->shiftRoster($tenantId, $sessionId)->select('students.id'))
            ->whereHas('guardian', fn (Builder $query) => $query->whereNotNull('parents.user_id'))
            ->with('guardian.user:id')
            ->get()
            ->pluck('guardian.user.id')
            ->filter()
            ->unique()
            ->values();
    }

    /** طلاب الجامع — مع حصرهم بالدوام المستهدف عند تحديده. */
    private function shiftRoster(string $tenantId, ?string $sessionId): Builder
    {
        return Student::query()
            ->withoutGlobalScope('study_session')
            ->where('students.tenant_id', $tenantId)
            ->active()
            ->when($sessionId, fn (Builder $query) => $query->where('students.study_session_id', $sessionId));
    }

    /** معرّف الجامع الفعلي (يعمل أيضاً عندما يدخل مدير الجوامع إلى جامع). */
    private function tenantId(Request $request): ?string
    {
        return config('app.current_tenant_id') ?: $request->user()->tenant_id;
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'تم حذف الإعلان');
    }
}
