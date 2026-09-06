<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HafizProfile;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use App\Services\QuranProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HafizController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly QuranProgramService $programs,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $profiles = HafizProfile::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.hafiz.index', [
            'profiles' => $profiles,
        ]);
    }

    public function profile(Student $student): View
    {
        $profile = $this->profileFor($student);

        return view('admin.quran.hafiz.profile', [
            'student' => $student,
            'profile' => $profile,
            'completion' => QuranCompletion::query()
                ->where('student_id', $student->id)
                ->where('status', 'confirmed')
                ->latest('confirmed_at')
                ->first(),
            'customFields' => $this->customFields->definitions(HafizProfile::CUSTOM_FIELD_ENTITY),
            'customValues' => $this->customFields->valuesFor(HafizProfile::CUSTOM_FIELD_ENTITY, $profile->id),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $profile = $this->profileFor($student);

        $data = $this->validated($request);
        $customPayload = $request->input('custom_fields', []);

        $this->customFields->validate(HafizProfile::CUSTOM_FIELD_ENTITY, $customPayload);

        $before = $profile->getAttributes();

        unset($data['custom_fields']);

        DB::transaction(function () use ($profile, $data, $customPayload) {
            $profile->update($data);

            $this->customFields->save(
                HafizProfile::CUSTOM_FIELD_ENTITY,
                $profile->id,
                $customPayload,
                $profile->tenant_id
            );
        });

        $this->audit->logModel('hafiz.profile.updated', $profile, $before, actor: $request->user());

        return redirect()
            ->route('admin.quran.hafiz.profile', $student)
            ->with('success', 'تم تحديث ملف الحافظ');
    }

    /** Ensure a profile row exists (repair) and return it. */
    public function profileFor(Student $student): HafizProfile
    {
        return $this->programs->ensureHafizProfile($student);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mujaz' => ['nullable', 'boolean'],
            'mujiz' => ['nullable', 'string', 'max:255'],
            'riwayah' => ['nullable', 'string', 'max:255'],
            'ijazah_jazariyyah' => ['nullable', 'boolean'],
            'scientific_certificate' => ['nullable', 'string', 'max:5000'],
            'sharia_courses' => ['nullable', 'string', 'max:5000'],
            'training_courses' => ['nullable', 'string', 'max:5000'],
            'sharia_academic_study' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'custom_fields' => ['nullable', 'array'],
        ]);
    }
}
