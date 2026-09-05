<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\QuranReviewSession;
use App\Models\QuranSurah;
use App\Models\RewardPoint;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase0RegressionTest extends TestCase
{
    private function tenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    private function teacherUser(Tenant $tenant, array $userAttributes = []): array
    {
        $user = User::factory()->for($tenant)->create($userAttributes);
        $teacher = Teacher::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        return [$user, $teacher];
    }

    public function test_total_points_subtracts_deductions(): void
    {
        $tenant = $this->tenant();
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $awarder = User::factory()->for($tenant)->create();

        RewardPoint::create(['student_id' => $student->id, 'awarded_by' => $awarder->id, 'points' => 10, 'type' => 'earned', 'reason' => 'أول']);
        RewardPoint::create(['student_id' => $student->id, 'awarded_by' => $awarder->id, 'points' => 5, 'type' => 'earned', 'reason' => 'ثاني']);
        RewardPoint::create(['student_id' => $student->id, 'awarded_by' => $awarder->id, 'points' => 3, 'type' => 'deducted', 'reason' => 'خصم']);

        $this->assertSame(12, $student->fresh()->totalPoints());
    }

    public function test_grades_cannot_be_changed_after_approval(): void
    {
        $tenant = $this->tenant();
        [$teacherUser] = $this->teacherUser($tenant);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الأول']);
        $subject = Subject::create(['tenant_id' => $tenant->id, 'name' => 'التجويد']);
        $teacher = Teacher::where('tenant_id', $tenant->id)->where('user_id', $teacherUser->id)->first();
        $exam = Exam::create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'title' => 'امتحان أول',
            'exam_date' => now()->toDateString(),
            'total_marks' => 100,
            'pass_marks' => 50,
        ]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id]);

        Sanctum::actingAs($teacherUser);

        $this->postJson("/api/v1/teacher/exams/{$exam->id}/grades", [
            'scores' => [$student->id => 80],
            'action' => 'submit',
        ])->assertOk();

        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/grades/{$exam->id}/approve")->assertOk();

        Sanctum::actingAs($teacherUser);

        $this->postJson("/api/v1/teacher/exams/{$exam->id}/grades", [
            'scores' => [$student->id => 90],
            'action' => 'submit',
        ])->assertStatus(422);

        $this->assertSame(80, (int) $exam->grades()->first()->score);
    }

    public function test_grades_reject_out_of_roster_students_and_over_max_scores(): void
    {
        $tenant = $this->tenant();
        [$teacherUser] = $this->teacherUser($tenant);
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'name' => 'الأول']);
        $subject = Subject::create(['tenant_id' => $tenant->id, 'name' => 'التجويد']);
        $teacher = Teacher::where('tenant_id', $tenant->id)->where('user_id', $teacherUser->id)->first();
        $exam = Exam::create([
            'tenant_id' => $tenant->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'title' => 'امتحان أول',
            'exam_date' => now()->toDateString(),
            'total_marks' => 100,
        ]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id, 'classroom_id' => $classroom->id]);

        Sanctum::actingAs($teacherUser);

        $this->postJson("/api/v1/teacher/exams/{$exam->id}/grades", [
            'scores' => [(string) Str::uuid() => 90],
            'action' => 'submit',
        ])->assertStatus(422);

        $this->postJson("/api/v1/teacher/exams/{$exam->id}/grades", [
            'scores' => [$student->id => 101],
            'action' => 'submit',
        ])->assertStatus(422);

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_attendance_rejects_unknown_student_keys(): void
    {
        $tenant = $this->tenant();
        [$teacherUser] = $this->teacherUser($tenant);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        Sanctum::actingAs($teacherUser);

        $this->postJson('/api/v1/teacher/attendance', [
            'date' => now()->toDateString(),
            'statuses' => [
                $student->id => 'present',
                (string) Str::uuid() => 'absent',
            ],
        ])->assertStatus(422);

        $this->postJson('/api/v1/teacher/attendance', [
            'date' => now()->toDateString(),
            'statuses' => [$student->id => 'present'],
        ])->assertOk();

        $this->assertDatabaseHas('attendances', ['student_id' => $student->id, 'status' => 'present']);
    }

    public function test_reward_point_delete_is_limited_to_own_manual_points(): void
    {
        $tenant = $this->tenant();
        [$teacherA] = $this->teacherUser($tenant);
        [$teacherB] = $this->teacherUser($tenant);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $ownPoint = RewardPoint::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'awarded_by' => $teacherA->id,
            'points' => 5,
            'reason' => 'اجتهاد',
            'type' => 'earned',
        ]);

        $otherPoint = RewardPoint::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'awarded_by' => $teacherB->id,
            'points' => 3,
            'reason' => 'نشاط',
            'type' => 'earned',
        ]);

        $surah = QuranSurah::create(['name_arabic' => 'الفاتحة', 'revelation_type' => 'makkiah', 'num_ayahs' => 7, 'sort_order' => 1]);
        $session = QuranReviewSession::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => Teacher::where('tenant_id', $tenant->id)->where('user_id', $teacherB->id)->first()->id,
            'student_id' => $student->id,
            'surah_id' => $surah->id,
            'from_ayah' => 1,
            'to_ayah' => 2,
            'date' => now()->toDateString(),
        ]);
        $autoPoint = RewardPoint::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'awarded_by' => $teacherB->id,
            'quran_review_session_id' => $session->id,
            'points' => 10,
            'reason' => 'تسميع',
            'type' => 'earned',
        ]);

        Sanctum::actingAs($teacherA);

        $this->deleteJson("/api/v1/teacher/reward-points/{$otherPoint->id}")->assertForbidden();
        $this->deleteJson("/api/v1/teacher/reward-points/{$autoPoint->id}")->assertForbidden();
        $this->deleteJson("/api/v1/teacher/reward-points/{$ownPoint->id}")->assertNoContent();

        $this->assertDatabaseMissing('reward_points', ['id' => $ownPoint->id]);
    }

    public function test_quran_review_rejects_out_of_range_ayahs(): void
    {
        $tenant = $this->tenant();
        [$teacherUser] = $this->teacherUser($tenant);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        $surah = QuranSurah::create(['name_arabic' => 'الفاتحة', 'revelation_type' => 'makkiah', 'num_ayahs' => 7, 'sort_order' => 1]);

        Sanctum::actingAs($teacherUser);

        $payload = [
            'surah_id' => $surah->id,
            'student_id' => $student->id,
            'date' => now()->toDateString(),
            'word_statuses' => [],
        ];

        $this->postJson('/api/v1/teacher/quran-review', [...$payload, 'from_ayah' => 5, 'to_ayah' => 3])
            ->assertStatus(422);

        $this->postJson('/api/v1/teacher/quran-review', [...$payload, 'from_ayah' => 1, 'to_ayah' => 10])
            ->assertStatus(422);

        $this->assertDatabaseCount('quran_review_sessions', 0);
    }

    public function test_admin_can_record_teacher_attendance(): void
    {
        $tenant = $this->tenant();
        [$teacherUser] = $this->teacherUser($tenant);
        $admin = User::factory()->admin()->for($tenant)->create();
        $teacher = Teacher::where('tenant_id', $tenant->id)->where('user_id', $teacherUser->id)->first();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/attendance/teachers', [
            'teacher_id' => $teacher->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ])->assertOk();

        $this->assertDatabaseHas('attendances', [
            'teacher_id' => $teacher->id,
            'student_id' => null,
            'status' => 'present',
        ]);
    }
}
