<?php

namespace Tests\Unit;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\ScheduleConflictService;
use Tests\TestCase;

/**
 * قاعدة التداخل الزمني وفحوصات التعارض الأساسية (معلم/شعبة/طالب).
 */
class ScheduleConflictServiceTest extends TestCase
{
    private function service(): ScheduleConflictService
    {
        return app(ScheduleConflictService::class);
    }

    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);

        return $mosque;
    }

    private function classroom(Tenant $mosque, string $name = 'الصف الأول'): Classroom
    {
        return Classroom::create(['tenant_id' => $mosque->id, 'name' => $name]);
    }

    private function section(Tenant $mosque, Classroom $classroom, string $name = 'أ'): Section
    {
        return Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => $name]);
    }

    private function schedule(Tenant $mosque, Classroom $classroom, array $attributes): Schedule
    {
        return Schedule::create(array_merge([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => Teacher::factory()->create(['tenant_id' => $mosque->id])->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ], $attributes));
    }

    // ------------------------------------------------------- overlap rule

    public function test_overlap_rule_covers_partial_full_and_adjacent_windows(): void
    {
        $this->assertTrue(ScheduleConflictService::timesOverlap('16:00', '18:00', '17:00', '19:00'));
        $this->assertTrue(ScheduleConflictService::timesOverlap('17:00', '19:00', '16:00', '18:00'));
        $this->assertTrue(ScheduleConflictService::timesOverlap('16:00', '18:00', '16:30', '17:30'));
        $this->assertTrue(ScheduleConflictService::timesOverlap('16:30', '17:30', '16:00', '18:00'));
        $this->assertTrue(ScheduleConflictService::timesOverlap('16:00', '18:00', '16:00', '18:00'));

        // Adjacent windows (16–18 then 18–20) do not conflict.
        $this->assertFalse(ScheduleConflictService::timesOverlap('16:00', '18:00', '18:00', '20:00'));
        $this->assertFalse(ScheduleConflictService::timesOverlap('18:00', '20:00', '16:00', '18:00'));
        $this->assertFalse(ScheduleConflictService::timesOverlap('16:00', '18:00', '19:00', '20:00'));

        // Mixed H:i / H:i:s inputs normalize before comparing.
        $this->assertTrue(ScheduleConflictService::timesOverlap('16:00', '18:00:00', '17:00:00', '19:00'));
    }

    // ------------------------------------------------------- teacher

    public function test_teacher_conflict_is_detected_across_study_sessions(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        $this->schedule($mosque, $this->classroom($mosque, 'الصف الأول'), [
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $conflicts = $this->service()->slotConflicts([
            'classroom_id' => $this->classroom($mosque, 'الصف الثاني')->id,
            'section_id' => null,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:30',
            'ends_at' => '09:30',
        ]);

        $this->assertNotEmpty($conflicts);
        $this->assertSame('teacher_id', $conflicts[0]['field']);
    }

    public function test_teacher_is_free_on_another_day_or_adjacent_time(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);
        $classroom = $this->classroom($mosque);

        $this->schedule($mosque, $classroom, [
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->assertSame([], $this->service()->slotConflicts([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]));

        $this->assertSame([], $this->service()->slotConflicts([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]));
    }

    // ------------------------------------------------------- section/classroom

    public function test_section_conflict_is_detected_for_the_same_section_only(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $sectionA = $this->section($mosque, $classroom, 'أ');
        $sectionB = $this->section($mosque, $classroom, 'ب');

        $this->schedule($mosque, $classroom, [
            'section_id' => $sectionA->id,
            'day_of_week' => 2,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
        ]);

        $sameSection = $this->service()->slotConflicts([
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
            'teacher_id' => Teacher::factory()->create(['tenant_id' => $mosque->id])->id,
            'day_of_week' => 2,
            'starts_at' => '10:30',
            'ends_at' => '11:30',
        ]);

        $otherSection = $this->service()->slotConflicts([
            'classroom_id' => $classroom->id,
            'section_id' => $sectionB->id,
            'teacher_id' => Teacher::factory()->create(['tenant_id' => $mosque->id])->id,
            'day_of_week' => 2,
            'starts_at' => '10:30',
            'ends_at' => '11:30',
        ]);

        $this->assertSame('classroom_id', $sameSection[0]['field']);
        $this->assertSame([], $otherSection);
    }

    public function test_whole_classroom_slot_conflicts_with_any_section_slot(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $section = $this->section($mosque, $classroom);

        $this->schedule($mosque, $classroom, [
            'section_id' => null,
            'day_of_week' => 3,
            'starts_at' => '07:00',
            'ends_at' => '08:00',
        ]);

        $conflicts = $this->service()->slotConflicts([
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'teacher_id' => Teacher::factory()->create(['tenant_id' => $mosque->id])->id,
            'day_of_week' => 3,
            'starts_at' => '07:30',
            'ends_at' => '08:30',
        ]);

        $this->assertNotEmpty($conflicts);
        $this->assertSame('classroom_id', $conflicts[0]['field']);
    }

    // ------------------------------------------------------- student

    public function test_student_conflict_is_detected_when_joining_a_section(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $sectionA = $this->section($mosque, $classroom, 'أ');
        $sectionB = $this->section($mosque, $classroom, 'ب');

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
        ]);

        $this->schedule($mosque, $classroom, [
            'section_id' => $sectionA->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->schedule($mosque, $classroom, [
            'section_id' => $sectionB->id,
            'day_of_week' => 0,
            'starts_at' => '08:30',
            'ends_at' => '09:30',
        ]);

        $details = $this->service()->studentConflictDetails($student, $sectionB);

        $this->assertTrue($this->service()->studentHasConflict($student, $sectionB));
        $this->assertNotEmpty($details);
    }

    public function test_student_without_conflicting_schedules_can_join(): void
    {
        $mosque = $this->mosque();
        $classroom = $this->classroom($mosque);
        $sectionA = $this->section($mosque, $classroom, 'أ');
        $sectionB = $this->section($mosque, $classroom, 'ب');

        $student = Student::factory()->create([
            'tenant_id' => $mosque->id,
            'classroom_id' => $classroom->id,
            'section_id' => $sectionA->id,
        ]);

        $this->schedule($mosque, $classroom, [
            'section_id' => $sectionA->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->schedule($mosque, $classroom, [
            'section_id' => $sectionB->id,
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->assertFalse($this->service()->studentHasConflict($student, $sectionB));
    }

    // ------------------------------------------------------- teacher view

    public function test_find_teacher_conflicts_reports_overlapping_pairs(): void
    {
        $mosque = $this->mosque();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id]);

        $this->schedule($mosque, $this->classroom($mosque, 'الأول'), [
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
        ]);

        $this->schedule($mosque, $this->classroom($mosque, 'الثاني'), [
            'teacher_id' => $teacher->id,
            'day_of_week' => 0,
            'starts_at' => '08:30',
            'ends_at' => '09:30',
        ]);

        $conflicts = $this->service()->findTeacherConflicts([$teacher->id]);

        $this->assertCount(1, $conflicts);
        $this->assertSame($teacher->id, $conflicts->first()['teacher_id']);
    }
}
