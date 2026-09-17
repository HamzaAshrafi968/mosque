<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ShariaCourseStatus;
use App\Enums\ShariaMemorizationStatus;
use App\Http\Controllers\Controller;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalNotification;
use App\Services\AuditLogger;
use App\Services\ShariaCourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * إنشاء الدورات الشرعية مركزياً من مدير الجوامع وربطها بجامع محدد،
 * مع إشعار مدير الجامع المستهدف (وأرشفتها في شاشات إدارة الجامع).
 */
class ShariaCourseController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ShariaCourseService $service,
    ) {}

    public function index(Request $request): View
    {
        $mosqueId = $request->input('mosque_id');
        $status = $request->input('status');
        $search = $request->input('search');

        $courses = ShariaCourse::withoutGlobalScope('tenant')
            ->with([
                'tenant:id,name',
                'supervisors' => fn ($query) => $query->withoutGlobalScopes(['tenant', 'study_session'])->select('teachers.id', 'teachers.name'),
            ])
            ->withCount([
                'students' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'lessons' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->when($mosqueId, fn ($query) => $query->where('tenant_id', $mosqueId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('super-admin.sharia-courses.index', [
            'courses' => $courses,
            'mosques' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ShariaCourseStatus::cases(),
            'mosqueId' => $mosqueId,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('super-admin.sharia-courses.create', [
            'mosques' => Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => ShariaCourseStatus::cases(),
            'memorizationStatuses' => ShariaMemorizationStatus::cases(),
        ]);
    }

    /** مشرفو الجامع وطلابه (JSON) لتعبئة نموذج الإنشاء المركزي. */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mosque_id' => ['required', 'uuid', 'exists:tenants,id'],
        ]);

        $mosqueId = $data['mosque_id'];

        $supervisors = Teacher::withoutGlobalScopes(['tenant', 'study_session'])
            ->where('tenant_id', $mosqueId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'specialty']);

        $students = Student::withoutGlobalScopes(['tenant', 'study_session'])
            ->where('tenant_id', $mosqueId)
            ->where('status', 'active')
            ->with('classroom:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'classroom_id']);

        return response()->json([
            'supervisors' => $supervisors,
            'students' => $students,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // تجاهل صفوف الطلاب الجدد الفارغة قبل التحقق.
        $request->merge([
            'new_students' => collect($request->input('new_students', []))
                ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
                ->values()
                ->all(),
        ]);

        $data = $request->validate([
            'mosque_id' => ['required', 'uuid', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'supervisor_ids' => ['nullable', 'array'],
            'supervisor_ids.*' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $request->input('mosque_id'))],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['nullable', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $request->input('mosque_id'))],
            'new_students' => ['nullable', 'array'],
            'new_students.*.name' => ['required', 'string', 'max:255'],
            'new_students.*.phone' => ['nullable', 'string', 'max:30'],
            'new_students.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'new_students.*.birth_date' => ['nullable', 'date'],
            'new_students.*.guardian_phone' => ['nullable', 'string', 'max:30'],
            'new_students.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mosque = Tenant::query()->findOrFail($data['mosque_id']);

        $course = DB::transaction(function () use ($data, $mosque, $request) {
            $course = ShariaCourse::create([
                'tenant_id' => $mosque->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? $mosque->name,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'source' => ShariaCourse::SOURCE_SUPER_ADMIN,
                'created_by' => $request->user()->id,
            ]);

            $course->supervisors()->sync($data['supervisor_ids'] ?? []);

            $this->service->syncEnrolledStudents($course, $data['student_ids'] ?? [], $request->user());

            foreach ($data['new_students'] ?? [] as $row) {
                $course->students()->create([
                    'tenant_id' => $mosque->id,
                    'name' => $row['name'],
                    'phone' => $row['phone'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'birth_date' => $row['birth_date'] ?? null,
                    'guardian_phone' => $row['guardian_phone'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'status' => ShariaCourseStudent::STATUS_ACTIVE,
                ]);
            }

            $this->audit->logModel('sharia_course.created', $course, actor: $request->user());

            return $course;
        });

        $this->notifyMosqueManagers($mosque, $course, $request->user());

        return redirect()
            ->route('super-admin.sharia-courses.index', ['mosque_id' => $mosque->id])
            ->with('success', 'تم إنشاء الدورة الشرعية في '.$mosque->name.' وإشعار مدير الجامع');
    }

    /**
     * إشعار مديري الجامع المستهدف. نُرسل مباشرة (لا عبر NotificationService)
     * لأن عملية الإرسال تعيد جلب المستخدمين بنطاق الجامع الحالي للمدير العام،
     * ما قد يُسقط مديري جامع آخر عند الإنشاء أثناء وجوده داخل جامع.
     */
    private function notifyMosqueManagers(Tenant $mosque, ShariaCourse $course, User $actor): void
    {
        $managers = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $mosque->id)
            ->where('role', User::ROLE_ADMIN)
            ->get();

        $url = route('admin.sharia-courses.show', ['course' => $course->id]);

        foreach ($managers as $manager) {
            $manager->notify(new PortalNotification(
                'دورة شرعية جديدة',
                'أضاف مدير الجوامع دورة «'.$course->name.'» إلى جامعكم',
                $url,
            ));
        }
    }
}
