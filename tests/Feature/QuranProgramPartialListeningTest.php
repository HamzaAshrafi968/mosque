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
use App\Models\QuranRecitationSession;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\StudentJuzMemorization;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * دورة التأهيلي/الإجازة (QuranProgramBatchService):
 *
 * - الاستماع الجزئي: تسجيل صفحات محددة من الجزء مع بقاء صفحات أخرى —
 *   تُحتسب في التغطية دون إنهاء الجزء، وتُكمل الجزء عند تغطية صفحاته.
 * - إعادة الاختبار بعد الرسوب: الراسب فقط (افتراضي) أو الاختبار التراكمي
 *   كاملًا عبر scope=full عند وجود تاريخ تراكمي.
 */
class QuranProgramPartialListeningTest extends TestCase
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

    private function engine(): QuranProgramBatchService
    {
        return app(QuranProgramBatchService::class);
    }

    private function programFor(Student $student, ProgramType $type = ProgramType::Qualifying): QuranListeningProgram
    {
        $enrollment = ProgramEnrollment::create([
            'student_id' => $student->id,
            'program_type' => $type,
            'started_at' => Carbon::today()->format('Y-m-d'),
            'status' => ProgramEnrollmentStatus::Active,
        ]);

        return app(QuranListeningProgramService::class)->ensureForEnrollment($enrollment);
    }

    private function batch(QuranListeningProgram $program, int $number): QuranListeningProgramBatch
    {
        return $program->batches()->where('batch_number', $number)->firstOrFail();
    }

    private function tasmeeAll(QuranListeningProgramBatch $batch, User $actor, Teacher $teacher): void
    {
        foreach ($batch->items()->orderBy('juz')->get() as $item) {
            if ($item->canBeListened() || $item->isNeedsRepeat()) {
                $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $actor, $teacher->id);
            }
        }
    }

    /** @param array<int, int> $juz */
    private function memorize(Student $student, array $juz): void
    {
        foreach ($juz as $number) {
            StudentJuzMemorization::create([
                'student_id' => $student->id,
                'juz' => $number,
                'memorized_at' => now()->toDateString(),
                'source' => StudentJuzMemorization::SOURCE_MANUAL,
            ]);
        }
    }

    private function linkTeacherToStudent(Tenant $mosque, Teacher $teacher, Student $student): void
    {
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
    }

    public function test_partial_listening_records_pages_without_completing_the_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();

        $partial = $this->engine()->recordPartialListening($item, [
            'from_page' => 1,
            'to_page' => 10,
            'date' => now()->toDateString(),
        ], $admin, $teacher->id);

        $this->assertSame($first->id, $partial->program_batch_id);
        $this->assertSame(1, (int) $partial->from_page);
        $this->assertSame(10, (int) $partial->to_page);
        $this->assertNull($partial->result);
        $this->assertNull($partial->word_statuses);

        // الجزء يبقى قيد التسميع ولم تُسجَّل عليه جلسة الاستماع.
        $this->assertSame(QuranListeningItemStatus::Available, $item->fresh()->status);
        $this->assertNull($item->fresh()->listened_at);
        $this->assertNull($item->fresh()->quran_recitation_session_id);

        // التغطية تحتسب الصفحات العشر فقط.
        $coverage = $this->engine()->juzCoverage($first->fresh());
        $this->assertSame(10, $coverage[1]['covered']);
        $this->assertFalse($coverage[1]['complete']);
        $this->assertSame(11, $this->engine()->remainingPages($item->fresh()));
    }

    public function test_partial_listening_accumulates_until_the_juz_is_complete(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();

        foreach ([[1, 10], [11, 21]] as [$from, $to]) {
            $this->engine()->recordPartialListening($item->fresh(), [
                'from_page' => $from,
                'to_page' => $to,
                'date' => now()->toDateString(),
            ], $admin, $teacher->id);
        }

        // اكتملت صفحات الجزء الأول → «تم التسميع» وبقية أجزاء الدفعة لم تُسمَّع بعد.
        $this->assertSame(QuranListeningItemStatus::Listened, $item->fresh()->status);
        $this->assertSame(0, $this->engine()->remainingPages($item->fresh()));
        $this->assertSame(21, $this->engine()->juzCoverage($first->fresh())[1]['covered']);
        $this->assertSame(QuranListeningBatchStatus::Listening, $first->fresh()->status);
    }

    public function test_partial_listening_is_rejected_outside_the_juz_range(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();

        // نطاق يتجاوز صفحات الجزء (الجزء 1: 1–21).
        try {
            $this->engine()->recordPartialListening($item, [
                'from_page' => 1,
                'to_page' => 22,
                'date' => now()->toDateString(),
            ], $admin, $teacher->id);
            $this->fail('نطاق خارج صفحات الجزء يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('from_page', $exception->errors());
        }

        // نطاق مقلوب.
        try {
            $this->engine()->recordPartialListening($item, [
                'from_page' => 15,
                'to_page' => 10,
                'date' => now()->toDateString(),
            ], $admin, $teacher->id);
            $this->fail('النطاق المقلوب يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('from_page', $exception->errors());
        }

        // جزء في دفعة مقفلة.
        $locked = $this->batch($program, 2)->items()->where('juz', 6)->firstOrFail();

        try {
            $this->engine()->recordPartialListening($locked, [
                'from_page' => 102,
                'to_page' => 110,
                'date' => now()->toDateString(),
            ], $admin, $teacher->id);
            $this->fail('الاستماع الجزئي في دفعة مقفلة يجب أن يُرفض');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }

        $this->assertSame(0, QuranRecitationSession::query()
            ->where('student_id', $student->id)
            ->whereNotNull('program_batch_id')
            ->count());
    }

    public function test_partial_listening_after_failure_counts_toward_the_retake(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $this->tasmeeAll($first, $admin, $teacher);

        $this->engine()->recordBatchTest($first->fresh(), [
            1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'fail',
        ], $admin);

        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);
        $this->assertSame([3, 5], $this->engine()->failedJuzNumbers($first->fresh()));

        // جلسات إعادة التسميع تُسجَّل بعد الاختبار الراسب.
        $this->travel(2)->seconds();

        $juzThree = $first->items()->where('juz', 3)->firstOrFail();
        $juzFive = $first->items()->where('juz', 5)->firstOrFail();

        // إعادة جزئية للجزء 3 ثم إكمالها، وإعادة كاملة للجزء 5.
        $this->engine()->recordPartialListening($juzThree->fresh(), [
            'from_page' => 42, 'to_page' => 50, 'date' => now()->toDateString(),
        ], $admin, $teacher->id);

        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $juzThree->fresh()->status);
        $this->assertSame(9, $this->engine()->retakeCoverage($first->fresh())[3]['covered']);

        $this->engine()->recordPartialListening($juzThree->fresh(), [
            'from_page' => 51, 'to_page' => 61, 'date' => now()->toDateString(),
        ], $admin, $teacher->id);

        $this->engine()->recordPartialListening($juzFive->fresh(), [
            'from_page' => 82, 'to_page' => 101, 'date' => now()->toDateString(),
        ], $admin, $teacher->id);

        $this->assertSame(QuranListeningItemStatus::Listened, $juzThree->fresh()->status);
        $this->assertSame(QuranListeningItemStatus::Listened, $juzFive->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::ReadyForTest, $first->fresh()->status);

        $retakeCoverage = $this->engine()->retakeCoverage($first->fresh());
        $this->assertTrue($retakeCoverage[3]['complete']);
        $this->assertTrue($retakeCoverage[5]['complete']);

        // إعادة الاختبار تشمل الراسبين فقط.
        $retest = $this->engine()->recordBatchTest($first->fresh(), [3 => 'pass', 5 => 'pass'], $admin);

        $this->assertTrue($retest->isPass());
        $this->assertSame([3, 5], $retest->items->pluck('juz')->sort()->values()->all());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }

    public function test_full_retake_option_reruns_the_cumulative_scope(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $this->tasmeeAll($first, $admin, $teacher);
        $this->engine()->recordBatchTest($first->fresh(), [3 => 'fail', 5 => 'fail', 1 => 'pass', 2 => 'pass', 4 => 'pass'], $admin);

        $this->assertTrue($this->engine()->fullRetakeAvailable($first->fresh()));
        $this->assertSame([1, 2, 3, 4, 5], $this->engine()->testScopeJuzNumbers($first->fresh(), 'full'));
        $this->assertSame([3, 5], $this->engine()->testScopeJuzNumbers($first->fresh()));

        $this->travel(2)->seconds();

        foreach ([3, 5] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $retest = $this->engine()->recordBatchTest($first->fresh(), [
            1 => 'pass', 2 => 'pass', 3 => 'pass', 4 => 'pass', 5 => 'pass',
        ], $admin, null, 'full');

        $this->assertTrue($retest->isPass());
        $this->assertSame([1, 2, 3, 4, 5], $retest->items->pluck('juz')->sort()->values()->all());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }

    public function test_full_retake_can_fail_a_previously_passed_juz(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $this->tasmeeAll($first, $admin, $teacher);
        $this->engine()->recordBatchTest($first->fresh(), [1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'fail'], $admin);

        $this->travel(2)->seconds();

        foreach ([3, 5] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        // إعادة كاملة: يرسُب جزءان كانا ناجحين سابقاً (2 و4).
        $this->engine()->recordBatchTest($first->fresh(), [
            1 => 'pass', 2 => 'fail', 3 => 'pass', 4 => 'fail', 5 => 'pass',
        ], $admin, null, 'full');

        $this->assertSame([2, 4], $this->engine()->failedJuzNumbers($first->fresh()));
        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $first->items()->where('juz', 2)->firstOrFail()->status);
        $this->assertSame(QuranListeningItemStatus::NeedsRepeat, $first->items()->where('juz', 4)->firstOrFail()->status);
        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $first->fresh()->status);
    }

    public function test_full_retake_is_ignored_without_cumulative_history(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        // رسوب من اختبار مباشر غير تراكمي على الدفعة الثانية (6–10).
        $this->memorize($student, range(1, 10));

        $first = $this->batch($program, 1);
        $second = $this->batch($program, 2);

        $results = [];

        foreach (range(1, 10) as $juz) {
            $results[$juz] = in_array($juz, [7, 8], true) ? 'fail' : 'pass';
        }

        $this->engine()->recordPlacementTest($first, $results, $admin);

        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
        $this->assertSame(QuranListeningBatchStatus::NeedsRepeat, $second->fresh()->status);

        $this->assertFalse($this->engine()->fullRetakeAvailable($second->fresh()));
        $this->assertSame([7, 8], $this->engine()->testScopeJuzNumbers($second->fresh(), 'full'));

        $this->travel(2)->seconds();

        foreach ([7, 8] as $juz) {
            $item = $second->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $retest = $this->engine()->recordBatchTest($second->fresh(), [7 => 'pass', 8 => 'pass'], $admin, null, 'full');

        $this->assertSame([7, 8], $retest->items->pluck('juz')->sort()->values()->all());
        $this->assertSame(QuranListeningBatchStatus::Passed, $second->fresh()->status);
    }

    public function test_admin_can_record_partial_listening_from_the_cycle_screen(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();
        $cycleUrl = route('admin.quran.programs.index', ['type' => 'qualifying', 'program_id' => $program->id]);

        $this->actingAs($admin)
            ->get($cycleUrl)
            ->assertOk()
            ->assertSee('تسجيل استماع جزئي')
            ->assertSee('حفظ الاستماع الجزئي');

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.items.partial', $item), [
                'from_page' => 1,
                'to_page' => 10,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect($cycleUrl)
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'المتبقي 11 صفحة'));

        $this->assertDatabaseHas('quran_recitation_sessions', [
            'student_id' => $student->id,
            'program_batch_id' => $first->id,
            'from_page' => 1,
            'to_page' => 10,
            'result' => null,
        ]);

        $this->assertSame(QuranListeningItemStatus::Available, $item->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.items.partial', $item), [
                'from_page' => 1,
                'to_page' => 22,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('from_page');
    }

    public function test_teacher_in_scope_can_record_partial_listening_and_out_of_scope_cannot(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $item = $first->items()->where('juz', 1)->firstOrFail();

        // أستاذ خارج نطاق الطالب.
        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.items.partial', $item), [
                'from_page' => 1,
                'to_page' => 10,
                'date' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->linkTeacherToStudent($mosque, $teacher, $student);

        $this->actingAs($teacherUser)
            ->post(route('teacher.quran.programs.items.partial', $item), [
                'from_page' => 1,
                'to_page' => 10,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect(route('teacher.quran.programs.index', ['type' => 'qualifying', 'program_id' => $program->id]));

        $this->assertSame(10, $this->engine()->juzCoverage($first->fresh())[1]['covered']);
    }

    public function test_retake_scope_choice_is_rendered_after_failure(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $program = $this->programFor($student);

        $first = $this->batch($program, 1);
        $cycleUrl = route('admin.quran.programs.index', ['type' => 'qualifying', 'program_id' => $program->id]);

        $this->actingAs($admin)
            ->get($cycleUrl)
            ->assertOk()
            ->assertDontSee('نطاق اختبار الإعادة');

        $this->tasmeeAll($first, $admin, $teacher);
        $this->engine()->recordBatchTest($first->fresh(), [
            1 => 'pass', 2 => 'pass', 3 => 'fail', 4 => 'pass', 5 => 'fail',
        ], $admin);

        $this->travel(2)->seconds();

        foreach ([3, 5] as $juz) {
            $item = $first->items()->where('juz', $juz)->firstOrFail();
            $this->engine()->recordTasmee($item->fresh(), ['date' => now()->toDateString()], $admin, $teacher->id);
        }

        $this->actingAs($admin)
            ->get($cycleUrl)
            ->assertOk()
            ->assertSee('نطاق اختبار الإعادة')
            ->assertSee('الأجزاء الراسبة فقط')
            ->assertSee('الاختبار التراكمي كاملًا')
            ->assertSee('أُعيد تسميع');

        // تسجيل الإعادة الكاملة من الواجهة.
        $results = [];

        foreach (range(1, 5) as $juz) {
            $results[$juz] = 'pass';
        }

        $this->actingAs($admin)
            ->post(route('admin.quran.programs.batches.test', $first), [
                'results' => $results,
                'scope' => 'full',
            ])
            ->assertRedirect();

        $test = $first->fresh()->lastTest;

        $this->assertNotNull($test);
        $this->assertTrue($test->isPass());
        $this->assertSame([1, 2, 3, 4, 5], $test->items->pluck('juz')->sort()->values()->all());
        $this->assertSame(QuranListeningBatchStatus::Passed, $first->fresh()->status);
    }
}
