<?php

namespace Tests\Feature;

use App\Enums\QuranTasmeeResult;
use App\Enums\QuranTeacherTimelineDetailLevel;
use App\Enums\QuranTeacherTimelineType;
use App\Models\QuranRecitationSession;
use App\Models\QuranReviewSession;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranKhamsaService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranTeacherTimelineService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «التسميع مع المعلم» — السجل الموحّد:
 *
 * - طبقة Presenter تحوّل التسميع (حكم عام) والاستماع التفصيلي (كلمة بكلمة)
 *   إلى View Model واحد مع إبقاء الكيانين مستقلين.
 * - الفلتر يُدفع داخل الاستعلامات (لا تجويع لمصدر لصالح آخر).
 * - الترتيب حتمي: الأحدث وقوعاً ثم إنشاءً ثم معرّفاً.
 */
class QuranTeacherTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(QuranDataSeeder::class);
    }

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
    private function teacher(Tenant $mosque, StudySession $session, string $name = 'الأستاذ محمد'): array
    {
        $user = User::factory()->create(['tenant_id' => $mosque->id]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'study_session_id' => $session->id,
            'name' => $name,
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

    private function recitation(Student $student, Teacher $teacher, array $attributes = []): QuranRecitationSession
    {
        return QuranRecitationSession::create(array_merge([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'type' => 'revision',
            'date' => now()->toDateString(),
            'amount' => 1,
        ], $attributes));
    }

    private function listening(Student $student, Teacher $teacher, array $attributes = []): QuranReviewSession
    {
        return QuranReviewSession::create(array_merge([
            'teacher_id' => $teacher->id,
            'student_id' => $student->id,
            'surah_id' => QuranSurah::query()->orderBy('sort_order')->firstOrFail()->id,
            'from_ayah' => 1,
            'to_ayah' => 5,
            'from_page' => 21,
            'to_page' => 25,
            'total_words' => 120,
            'correct_words' => 117,
            'incorrect_words' => 3,
            'hesitation_words' => 2,
            'tajweed_error_words' => 1,
            'added_words' => 0,
            'forgotten_words' => 0,
            'mastery_percentage' => 94,
            'date' => now()->toDateString(),
        ], $attributes));
    }

    public function test_service_maps_both_session_kinds_into_unified_items(): void
    {
        [$mosque, , $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->recitation($student, $teacher, [
            'type' => 'new',
            'from_page' => 21,
            'to_page' => 25,
            'amount' => 5,
            'result' => QuranTasmeeResult::Excellent->value,
        ]);

        $this->recitation($student, $teacher, [
            'type' => 'revision',
            'amount' => 1,
            'recited_portion' => 'جزء عم',
        ]);

        $this->listening($student, $teacher);

        $items = app(QuranTeacherTimelineService::class)->forStudent($student);

        $this->assertCount(3, $items);

        $new = $items->firstWhere('type', QuranTeacherTimelineType::RecitationNew);
        $this->assertNotNull($new);
        $this->assertSame('صفحات 21–25', $new->pagesLabel);
        $this->assertSame(QuranTasmeeResult::Excellent, $new->result);
        $this->assertSame(QuranTeacherTimelineDetailLevel::Summary, $new->detailLevel);
        $this->assertSame('الأستاذ محمد', $new->teacherName);

        $revision = $items->firstWhere('type', QuranTeacherTimelineType::RecitationRevision);
        $this->assertNotNull($revision);
        $this->assertSame('المقدار: 1', $revision->amountLabel);
        $this->assertSame('جزء عم', $revision->subtitle);

        $listening = $items->firstWhere('type', QuranTeacherTimelineType::TeacherListening);
        $this->assertNotNull($listening);
        $this->assertSame(QuranTeacherTimelineDetailLevel::Detailed, $listening->detailLevel);
        $this->assertSame('صفحات 21–25', $listening->pagesLabel);
        $this->assertSame(94.0, $listening->masteryPercentage);
        $this->assertSame(3, $listening->errorStats['word_errors']);
        $this->assertSame(1, $listening->errorStats['tajweed_errors']);
        $this->assertTrue($listening->hasErrors());
        $this->assertStringContainsString('3 كلمات', $listening->errorSummary());
        $this->assertStringContainsString('خطأ تجويد', $listening->errorSummary());
        $this->assertStringContainsString('2 تردد', $listening->errorSummary());
    }

    public function test_timeline_orders_latest_first_and_is_deterministic(): void
    {
        [$mosque, , $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->recitation($student, $teacher, ['date' => now()->subDays(3)->toDateString()]);
        $this->listening($student, $teacher, ['date' => now()->subDay()->toDateString()]);
        $this->recitation($student, $teacher, ['type' => 'new', 'date' => now()->toDateString()]);

        $service = app(QuranTeacherTimelineService::class);

        $first = $service->forStudent($student);
        $second = $service->forStudent($student);

        $this->assertSame(
            [
                QuranTeacherTimelineType::RecitationNew->value,
                QuranTeacherTimelineType::TeacherListening->value,
                QuranTeacherTimelineType::RecitationRevision->value,
            ],
            $first->pluck('type')->map(fn ($type) => $type->value)->all()
        );

        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
    }

    public function test_listening_filter_does_not_starve_older_review_sessions(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        // 40 تسميعاً حديثاً + جلسة استماع واحدة قديمة: الفلتر يجب ألا يخفيها.
        for ($i = 0; $i < 40; $i++) {
            $this->recitation($student, $teacher);
        }

        $oldListening = $this->listening($student, $teacher, [
            'date' => now()->subMonth()->toDateString(),
        ]);

        $items = app(QuranTeacherTimelineService::class)->forStudent($student, filter: 'listening');

        $this->assertCount(1, $items);
        $this->assertSame($oldListening->id, $items->first()->id);
        $this->assertSame(QuranTeacherTimelineType::TeacherListening, $items->first()->type);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', [
                'student_id' => $student->id,
                'timeline_type' => 'listening',
            ]))
            ->assertOk()
            ->assertSee('استماع مع المعلم')
            ->assertDontSee('تسميع مراجعة');
    }

    public function test_timeline_is_scoped_to_the_teacher_when_teacher_id_is_given(): void
    {
        [$mosque, , $session] = $this->mosque();
        [, $first] = $this->teacher($mosque, $session, 'الأستاذ الأول');
        [, $second] = $this->teacher($mosque, $session, 'الأستاذ الثاني');
        $student = $this->student($mosque, $session);

        $this->recitation($student, $first);
        $this->recitation($student, $second);
        $this->listening($student, $second);

        $items = app(QuranTeacherTimelineService::class)->forStudent($student, teacherId: $first->id);

        $this->assertCount(1, $items);
        $this->assertSame('الأستاذ الأول', $items->first()->teacherName);
    }

    public function test_batches_center_renders_the_unified_timeline_with_badges_and_filters(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        $this->recitation($student, $teacher, ['type' => 'new', 'from_page' => 21, 'to_page' => 25, 'amount' => 5]);
        $this->listening($student, $teacher);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('التسميع مع المعلم')
            ->assertSee('تسميع جديد')
            ->assertSee('استماع مع المعلم')
            ->assertSee('صفحات 21–25')
            ->assertSee('عرض الجلسة')
            ->assertSee('تعديل')
            ->assertSee('timeline_type=listening', false);
    }

    public function test_student_profile_renders_the_unified_log_read_only(): void
    {
        [$mosque, , $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, 'أحمد');
        $student->update(['user_id' => $studentUser->id]);

        $this->recitation($student, $teacher, ['type' => 'new', 'from_page' => 21, 'to_page' => 25, 'amount' => 5]);
        $this->listening($student, $teacher);

        $this->actingAs($studentUser)
            ->get(route('student.quran-profile'))
            ->assertOk()
            ->assertSee('سجل التسميع مع المعلم')
            ->assertSee('تسميع جديد')
            ->assertSee('استماع مع المعلم')
            ->assertDontSee('عرض الجلسة')
            ->assertDontSee('>تعديل<', false);
    }

    public function test_admin_center_exposes_one_unified_session_button(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->recitation($student, $teacher);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('+ التسميع مع المعلم')
            ->assertSee(route('admin.quran.batches.session-start', ['student_id' => $student->id]), false)
            ->assertDontSee('+ تسجيل تسميع')
            ->assertDontSee('+ جلسة استماع');
    }

    public function test_teacher_center_exposes_one_unified_session_button(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->recitation($student, $teacher);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.index', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('+ التسميع مع المعلم')
            ->assertDontSee('+ تسجيل تسميع')
            ->assertDontSee('+ جلسة استماع');
    }

    public function test_teacher_chooser_offers_all_three_session_types(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->recitation($student, $teacher);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.session-start', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('تسميع حفظ جديد')
            ->assertSee('تسميع مراجعة')
            ->assertSee('استماع وتقييم تفصيلي')
            ->assertSee('type=new', false)
            ->assertSee('type=revision', false)
            ->assertSee(route('teacher.quran-review.create'), false);
    }

    public function test_admin_chooser_hides_listening_and_shows_batch_context(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);

        app(QuranKhamsaService::class)->recordMemorization($student, 1);
        app(QuranMemorizationGatingService::class)->sync($student);

        $this->actingAs($admin)
            ->get(route('admin.quran.batches.session-start', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('تسميع حفظ جديد')
            ->assertSee('تسميع مراجعة')
            ->assertDontSee('استماع وتقييم تفصيلي')
            ->assertSee('الدفعة الحالية');
    }

    public function test_chooser_rejects_out_of_scope_student_for_teacher(): void
    {
        [$mosque, , $session] = $this->mosque();
        [$teacherUser] = $this->teacher($mosque, $session);
        $outsider = $this->student($mosque, $session, 'طالب خارج النطاق');

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.batches.session-start', ['student_id' => $outsider->id]))
            ->assertForbidden();
    }

    public function test_chooser_rejects_roles_without_quran_permissions(): void
    {
        [$mosque, , $session] = $this->mosque();
        $student = $this->student($mosque, $session);
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);

        $this->actingAs($studentUser)
            ->get(route('teacher.quran.batches.session-start', ['student_id' => $student->id]))
            ->assertForbidden();
    }

    public function test_tasmee_create_preselects_session_type_from_unified_entry(): void
    {
        [$mosque, $admin, $session] = $this->mosque();
        [$teacherUser, $teacher] = $this->teacher($mosque, $session);
        $student = $this->student($mosque, $session);
        $this->recitation($student, $teacher);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.tasmee.create', ['student_id' => $student->id, 'type' => 'new']))
            ->assertOk()
            ->assertSee('value="new" selected', false)
            ->assertDontSee('value="revision" selected', false);

        $this->actingAs($teacherUser)
            ->get(route('teacher.quran.tasmee.create', ['student_id' => $student->id, 'type' => 'revision']))
            ->assertOk()
            ->assertSee('value="revision" selected', false);

        $this->actingAs($admin)
            ->get(route('admin.quran.tasmee.create', ['student_id' => $student->id, 'type' => 'new']))
            ->assertOk()
            ->assertSee('value="new" selected', false);
    }
}
