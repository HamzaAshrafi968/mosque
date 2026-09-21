<?php

namespace Tests\Feature;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranListeningBatchStatus;
use App\Enums\QuranListeningItemStatus;
use App\Models\Classroom;
use App\Models\ProgramEnrollment;
use App\Models\QuranListeningProgram;
use App\Models\QuranListeningProgramBatch;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «برامج الاستماع» (الإجازة/التأهيلي):
 *
 * - النوعان فقط بعد إزالة البرنامج التدريبي، ولا مسار تسجيل يدوي.
 * - الدورة تُولَّد كسولاً لالتحاق البرنامج النشط (6 دفعات × 5 أجزاء).
 * - الواجهات تعرض دورة الدفعات التراكمية، والتسميع/الاختبار للأستاذ/المدير.
 */
class QuranListeningProgramTest extends TestCase
{
    /** @return array{0: Tenant, 1: User, 2: StudySession} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();
        $session = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->firstOrFail();

        return [$mosque, $admin, $session];
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, StudySession $session): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'study_session_id' => $session->id,
            'name' => 'الأستاذ محمد',
        ]);

        return [$user, $teacher];
    }

    private function student(Tenant $mosque, StudySession $session, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
            'name' => $name,
        ]);
    }

    private function service(): QuranListeningProgramService
    {
        return app(QuranListeningProgramService::class);
    }

    private function programFor(Student $student, ProgramType $type): QuranListeningProgram
    {
        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => $type,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        return $this->service()->ensureForEnrollment($enrollment);
    }

    private function batch(QuranListeningProgram $program, int $number): QuranListeningProgramBatch
    {
        return $program->batches()->where('batch_number', $number)->firstOrFail();
    }

    public function test_training_program_type_is_removed(): void
    {
        $this->assertNull(ProgramType::tryFrom('training'));

        $this->assertSame(
            ['qualifying', 'ijazah', 'readings'],
            array_map(fn (ProgramType $type) => $type->value, ProgramType::cases()),
        );

        $this->assertFalse(Route::has('admin.quran.programs.store'));
        $this->assertFalse(Route::has('teacher.quran.programs.store'));
    }

    public function test_removal_migration_purges_training_data_and_permission(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $programId = (string) Str::uuid();
        $batchId = (string) Str::uuid();
        $testId = (string) Str::uuid();
        $enrollmentId = (string) Str::uuid();
        $permissionId = (string) Str::uuid();

        DB::table('program_enrollments')->insert([
            'id' => $enrollmentId,
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'program_type' => 'training',
            'started_at' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quran_listening_programs')->insert([
            'id' => $programId,
            'tenant_id' => $mosque->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollmentId,
            'type' => 'training',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quran_listening_program_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $mosque->id,
            'program_id' => $programId,
            'student_id' => $student->id,
            'batch_number' => 1,
            'from_juz' => 1,
            'to_juz' => 5,
            'status' => 'listening',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quran_listening_tests')->insert([
            'id' => $testId,
            'tenant_id' => $mosque->id,
            'listening_batch_id' => $batchId,
            'student_id' => $student->id,
            'tested_by' => $admin->id,
            'tested_at' => now(),
            'result' => 'pass',
            'score' => 100,
            'passing_percentage' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')->insert([
            'id' => $permissionId,
            'code' => 'quran_training.create',
            'resource' => 'quran_training',
            'action' => 'create',
            'label' => 'تسجيل طالب في البرنامج التدريبي',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_09_19_000001_remove_training_program_type.php'))->up();

        $this->assertSame(0, DB::table('quran_listening_programs')->where('id', $programId)->count());
        $this->assertSame(0, DB::table('quran_listening_program_batches')->where('id', $batchId)->count());
        $this->assertSame(0, DB::table('quran_listening_tests')->where('id', $testId)->count());
        $this->assertSame(0, DB::table('program_enrollments')->where('id', $enrollmentId)->count());
        $this->assertSame(0, DB::table('permissions')->where('id', $permissionId)->count());
    }

    public function test_qualifying_enrollment_gets_a_listening_cycle_lazily(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);

        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => ProgramType::Qualifying,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        $programs = $this->service()->programsForStudent($student);
        $program = $programs->firstWhere('type', ProgramType::Qualifying);

        $this->assertNotNull($program);
        $this->assertSame($enrollment->id, $program->enrollment_id);
        $this->assertSame(6, $program->batches()->count());
        $this->assertSame(30, $program->items()->count());

        // Idempotent: لا تتكرر الدورة.
        $this->service()->programsForStudent($student);

        $this->assertSame(1, QuranListeningProgram::query()
            ->where('student_id', $student->id)
            ->where('type', ProgramType::Qualifying)
            ->count());
    }

    public function test_teacher_in_scope_can_record_tasmee_and_test(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'شعبة']);

        SectionTeacher::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'role' => 'lead',
            'status' => 'active',
        ]);

        $student->update(['classroom_id' => $classroom->id, 'section_id' => $section->id]);

        SectionStudent::create([
            'tenant_id' => $mosque->id,
            'section_id' => $section->id,
            'student_id' => $student->id,
            'status' => 'active',
            'enrolled_at' => now()->toDateString(),
        ]);

        $program = $this->programFor($student, ProgramType::Qualifying);
        $first = $this->batch($program, 1);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.programs.index', ['type' => 'qualifying', 'student_id' => $student->id]))
            ->assertOk();

        foreach ($first->items()->orderBy('juz')->get() as $item) {
            $this->actingAs($teacherUser)
                ->post(route('teacher.quran.programs.items.tasmee.store', $item), ['date' => now()->toDateString()])
                ->assertRedirect();
        }

        $results = [];

        foreach (range(1, 5) as $juz) {
            $results[$juz] = 'pass';
        }

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.batches.test', $first), ['results' => $results])
            ->assertRedirect();

        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }

    public function test_admin_center_lists_only_qualifying_and_ijazah(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index'))
            ->assertOk()
            ->assertSee('برامج الاستماع')
            ->assertSee('البرنامج التأهيلي')
            ->assertSee('برنامج الإجازة')
            ->assertDontSee('البرنامج التدريبي');

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => $program->type->value, 'program_id' => $program->id]))
            ->assertOk()
            ->assertSee('الاختبار التراكمي')
            ->assertSee('التسميع مع المعلم')
            ->assertSee('خريطة الأجزاء الثلاثين');
    }

    public function test_program_path_url_redirects_to_the_canonical_query_url(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.show', $program))
            ->assertRedirect(route('admin.quran.programs.index', ['type' => 'qualifying', 'program_id' => $program->id]));
    }

    public function test_programs_center_links_students_to_their_program_by_id(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->actingAs($admin)
            ->get(route('admin.quran.programs.index', ['type' => 'qualifying']))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('program_id='.$program->id, false);
    }

    public function test_admin_qualifying_page_links_students_to_their_program_by_id(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student, ProgramType::Qualifying);

        $this->actingAs($admin)
            ->get(route('admin.quran.qualifying.index'))
            ->assertOk()
            ->assertSee('program_id='.$program->id, false);
    }

    public function test_teacher_and_student_access_is_isolated(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $other = $this->student($mosque, $session, 'طالب آخر');

        $program = $this->programFor($student, ProgramType::Qualifying);
        $item = $this->batch($program, 1)->items()->firstOrFail();

        // الأستاذ بلا شعب → خارج النطاق.
        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.programs.index', ['student_id' => $student->id]))
            ->assertForbidden();

        // طالب آخر لا يرى برنامج غيره في بوابته.
        $otherUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $other->update(['user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->get(route('student.quran-programs.index'))
            ->assertOk()
            ->assertDontSee('الطالب أحمد');

        $this->assertSame(QuranListeningItemStatus::Available, $item->fresh()->status);
    }
}
