<?php

namespace Tests\Feature;

use App\Models\QuranListeningPlan;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuranListeningService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Database\Seeders\QuranDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * قائمة تشغيل «خطة الاستماع»: بناء مقاطع الآيات من نطاق الصفحات مع روابط
 * القارئ الخارجي، وربط كل مقطع بصفحته وسورته ونصه.
 */
class QuranListeningAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(QuranDataSeeder::class);
    }

    /** @return array{0: Tenant, 1: User, 2: StudySession, 3: Teacher} */
    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        app(RoleService::class)->provisionTenantRoles($mosque);
        app(StudySessionService::class)->provisionTenantSessions($mosque);

        $admin = User::factory()->admin()->for($mosque)->create();
        $session = StudySession::where('tenant_id', $mosque->id)->orderBy('name')->firstOrFail();

        $teacherUser = User::factory()->create(['tenant_id' => $mosque->id]);
        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque->id,
            'user_id' => $teacherUser->id,
            'study_session_id' => $session->id,
        ]);

        return [$mosque, $admin, $session, $teacher];
    }

    private function student(Tenant $mosque, StudySession $session, ?User $user = null, string $name = 'الطالب أحمد'): Student
    {
        return Student::factory()->create([
            'tenant_id' => $mosque->id,
            'study_session_id' => $session->id,
            'user_id' => $user?->id,
            'name' => $name,
        ]);
    }

    /** @param array<int, array{juz: int, from_page: int, to_page: int}> $items */
    private function createPlan(Student $student, Teacher $teacher, StudySession $session, array $items, User $actor): QuranListeningPlan
    {
        return app(QuranListeningService::class)->createPlan([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'study_session_id' => $session->id,
            'items' => $items,
        ], $actor);
    }

    public function test_audio_endpoint_returns_per_ayah_tracks_with_urls_and_text(): void
    {
        [$mosque, $admin, $session, $teacher] = $this->mosque();
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 2],
        ], $admin);

        $item = $plan->items()->firstOrFail();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.quran.listening.items.audio', $item))
            ->assertOk();

        $payload = $response->json();

        $this->assertSame(1, $payload['juz']);
        $this->assertSame(1, $payload['from_page']);
        $this->assertSame(2, $payload['to_page']);
        $this->assertCount(12, $payload['tracks']);

        $first = $payload['tracks'][0];
        $this->assertSame(1, $first['page']);
        $this->assertSame(1, $first['surah']);
        $this->assertSame(1, $first['ayah']);
        $this->assertNotSame('', $first['surah_name']);
        $this->assertStringEndsWith('/001001.mp3', $first['url']);
        $this->assertStringContainsString('everyayah.com', $first['url']);
        $this->assertNotSame('', $first['text']);

        $last = $payload['tracks'][11];
        $this->assertSame(2, $last['page']);
        $this->assertSame(2, $last['surah']);
        $this->assertStringEndsWith('/002005.mp3', $last['url']);
    }

    public function test_audio_endpoint_respects_the_selected_reciter(): void
    {
        [$mosque, $admin, $session, $teacher] = $this->mosque();
        $student = $this->student($mosque, $session);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 30, 'from_page' => 582, 'to_page' => 582],
        ], $admin);

        $item = $plan->items()->firstOrFail();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.quran.listening.items.audio', ['item' => $item, 'reciter' => 'ar.husary']))
            ->assertOk();

        $this->assertSame('ar.husary', $response->json('reciter'));
        $this->assertStringContainsString('Husary_128kbps', $response->json('tracks.0.url'));
    }

    public function test_student_audio_is_scoped_to_his_own_plan(): void
    {
        [$mosque, $admin, $session, $teacher] = $this->mosque();

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, $studentUser);
        $otherStudent = $this->student($mosque, $session, null, 'طالب آخر');

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 1],
        ], $admin);

        $otherPlan = $this->createPlan($otherStudent, $teacher, $session, [
            ['juz' => 1, 'from_page' => 1, 'to_page' => 1],
        ], $admin);

        $ownItem = $plan->items()->firstOrFail();
        $otherItem = $otherPlan->items()->firstOrFail();

        $this->actingAs($studentUser)
            ->getJson(route('student.quran-listening.items.audio', $ownItem))
            ->assertOk()
            ->assertJsonPath('from_page', 1);

        $this->actingAs($studentUser)
            ->getJson(route('student.quran-listening.items.audio', $otherItem))
            ->assertForbidden();
    }

    public function test_student_show_page_renders_the_player_and_parts(): void
    {
        [$mosque, $admin, $session, $teacher] = $this->mosque();

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => 'student']);
        $student = $this->student($mosque, $session, $studentUser);

        $plan = $this->createPlan($student, $teacher, $session, [
            ['juz' => 1, 'from_page' => 15, 'to_page' => 21],
            ['juz' => 2, 'from_page' => 22, 'to_page' => 26],
        ], $admin);

        $this->actingAs($studentUser)
            ->get(route('student.quran-listening.show', $plan))
            ->assertOk()
            ->assertSee('خطة استماع')
            ->assertSee('صفحات 15–21')
            ->assertSee('تم الاستماع')
            ->assertSee('data-listening-player', false)
            ->assertSee('مقفل');
    }
}
