<?php

namespace App\Services;

use App\Enums\SectionStudentStatus;
use App\Enums\SectionTeacherRole;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Validation\ValidationException;

/**
 * Section membership operations (spec §6-§7).
 *
 * - `section_students` preserves enrollment history; transfers close the old
 *   row (status = transferred) and open a new one.
 * - `students.section_id/classroom_id` are kept as the *current snapshot* for
 *   the existing rosters/reports; they are maintained here so the two stay in
 *   sync.
 * - `section_teachers` is the explicit assignment used for teacher scoping.
 *
 * All actions are audited through AuditLogger.
 */
class EnrollmentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Active enrollment row for a student (null when none). */
    public function currentMembership(Student $student): ?SectionStudent
    {
        return $student->enrollments()
            ->where('status', SectionStudentStatus::Active)
            ->latest('created_at')
            ->first();
    }

    /**
     * Enroll an unassigned student into a section.
     *
     * @throws ValidationException when the student is already active elsewhere
     */
    public function enroll(Student $student, Section $section): SectionStudent
    {
        if ($section->tenant_id !== $student->tenant_id) {
            throw ValidationException::withMessages(['section_id' => ['الشعبة لا تنتمي لنفس الجامع']]);
        }

        $current = $this->currentMembership($student);

        if ($current && $current->section_id === $section->id) {
            return $current;
        }

        if ($current) {
            throw ValidationException::withMessages([
                'section_id' => ['الطالب مسجل بالفعل في شعبة أخرى — استخدم النقل لتغيير شعبته'],
            ]);
        }

        $membership = SectionStudent::firstOrNew([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'section_id' => $section->id,
        ]);

        $wasNew = ! $membership->exists;

        if ($membership->status === SectionStudentStatus::Active) {
            $this->syncStudentSnapshot($student, $section);

            return $membership;
        }

        $membership->fill([
            'status' => SectionStudentStatus::Active,
            'enrolled_at' => $membership->enrolled_at ?? now()->toDateString(),
            'left_at' => null,
        ])->save();

        $this->syncStudentSnapshot($student, $section);
        $this->audit->logModel(
            $wasNew ? 'student.enrolled' : 'student.enrollment.reactivated',
            $membership,
            after: $membership->only(['section_id', 'student_id', 'status', 'enrolled_at'])
        );

        return $membership;
    }

    /**
     * Transfer a student to another section, closing the previous membership
     * with status=transferred and opening a new active one.
     *
     * @return array{0: SectionStudent, 1: SectionStudent} [closed, opened]
     *
     * @throws ValidationException
     */
    public function transfer(Student $student, Section $toSection): array
    {
        if ($toSection->tenant_id !== $student->tenant_id) {
            throw ValidationException::withMessages(['section_id' => ['الشعبة لا تنتمي لنفس الجامع']]);
        }

        $current = $this->currentMembership($student);

        if ($current && $current->section_id === $toSection->id) {
            throw ValidationException::withMessages(['section_id' => ['الطالب مسجل في هذه الشعبة بالفعل']]);
        }

        $today = now()->toDateString();

        if ($current) {
            $current->update([
                'status' => SectionStudentStatus::Transferred,
                'left_at' => $today,
            ]);
        }

        $opened = $this->enroll($student, $toSection);

        // enroll() above already syncs the snapshot; audit both sides of the move.
        $this->audit->log('student.transferred', 'student', $student->id, $student->tenant_id, [
            'from_section_id' => $current?->section_id,
            'to_section_id' => $toSection->id,
        ]);

        return [$current, $opened];
    }

    /**
     * Remove a student from their current section (membership becomes
     * inactive, snapshot cleared). The student profile itself stays.
     */
    public function removeFromSection(Student $student): ?SectionStudent
    {
        $current = $this->currentMembership($student);

        if (! $current) {
            return null;
        }

        $current->update([
            'status' => SectionStudentStatus::Inactive,
            'left_at' => now()->toDateString(),
        ]);

        $student->update([
            'classroom_id' => null,
            'section_id' => null,
        ]);

        $this->audit->log('student.removed_from_section', 'section_student', $current->id, $student->tenant_id, [
            'section_id' => $current->section_id,
            'student_id' => $student->id,
        ]);

        return $current;
    }

    /**
     * Reconcile a student's current placement (called by student create/edit).
     * When the chosen section differs from the membership, the move is
     * recorded as a transfer so the history stays truthful.
     */
    public function syncPlacement(Student $student, ?string $sectionId): void
    {
        $section = $sectionId ? Section::find($sectionId) : null;

        if ($section && $section->tenant_id !== $student->tenant_id) {
            throw ValidationException::withMessages(['section_id' => ['الشعبة لا تنتمي لنفس الجامع']]);
        }

        $current = $this->currentMembership($student);

        if (! $section) {
            if ($current && $student->section_id === null) {
                // Snapshot cleared but membership row stale — close it too.
                $this->removeFromSection($student);
            }

            return;
        }

        if ($current && $current->section_id === $section->id) {
            $this->syncStudentSnapshot($student, $section);

            return;
        }

        if ($current) {
            $this->transfer($student, $section);
        } else {
            $this->enroll($student, $section);
        }
    }

    /** Keep the denormalised current snapshot on `students` in sync. */
    public function syncStudentSnapshot(Student $student, Section $section): void
    {
        if ($student->section_id !== $section->id || $student->classroom_id !== $section->classroom_id) {
            $student->update([
                'section_id' => $section->id,
                'classroom_id' => $section->classroom_id,
            ]);
        }
    }

    /** Assign a teacher to a section (idempotent reactivation). */
    public function assignTeacher(Section $section, Teacher $teacher, SectionTeacherRole $role = SectionTeacherRole::Lead): SectionTeacher
    {
        if ($teacher->tenant_id !== $section->tenant_id) {
            throw ValidationException::withMessages(['teacher_id' => ['المعلم لا ينتمي لنفس الجامع']]);
        }

        $assignment = SectionTeacher::firstOrNew([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
        ]);

        $assignment->fill([
            'role' => $role,
            'status' => 'active',
            'starts_at' => $assignment->starts_at ?? now()->toDateString(),
            'ends_at' => null,
        ])->save();

        $this->audit->log('teacher.assigned_to_section', 'section_teacher', $assignment->id, $section->tenant_id, [
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'role' => $role->value,
        ]);

        return $assignment;
    }

    /** Remove a teacher from a section (soft close, keeps history). */
    public function removeTeacher(Section $section, Teacher $teacher): void
    {
        $assignment = SectionTeacher::query()
            ->where('section_id', $section->id)
            ->where('teacher_id', $teacher->id)
            ->first();

        if (! $assignment) {
            return;
        }

        $assignment->update([
            'status' => 'inactive',
            'ends_at' => now()->toDateString(),
        ]);

        $this->audit->log('teacher.removed_from_section', 'section_teacher', $assignment->id, $section->tenant_id, [
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
        ]);
    }
}
