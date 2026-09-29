<?php

namespace Tests\Feature;

use App\Enums\ShariaMemorizationStatus;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الإنشاء المركزي للدورات الشرعية من مدير المساجد: اختيار الجامع والمشرفين
 * والطلاب (موجودين + جدد)، إشعار مدير الجامع، وربط الطلاب وحالة الحفظ.
 */
class ShariaCourseCentralTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): array
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        $manager = User::factory()->admin()->for($mosque)->create();

        return [$mosque, $manager];
    }

    private function superAdmin(): User
    {
        config(['app.current_tenant_id' => null]);

        $superAdmin = User::factory()->create(['tenant_id' => null, 'role' => User::ROLE_SUPER_ADMIN]);
        app(RoleService::class)->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        return $superAdmin;
    }

    private function makeTeacher(string $tenantId, string $name = 'مشرف الدورة'): array
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $teacher = Teacher::factory()->create(['tenant_id' => $tenantId, 'user_id' => $user->id, 'name' => $name]);

        return [$user, $teacher];
    }

    public function test_super_admin_creates_course_for_a_mosque_and_notifies_its_manager(): void
    {
        [$mosque, $manager] = $this->mosque();
        [, $otherManager] = $this->mosque();
        [, $supervisor] = $this->makeTeacher($mosque->id, 'المشرف الأول');

        $existingStudent = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'طالب موجود']);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف الفقه']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'شعبة أ']);

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.sharia-courses.store'), [
                'mosque_id' => $mosque->id,
                'name' => 'دورة الفقه المركزي',
                'description' => 'دورة أضافها مدير المساجد',
                'location' => 'القاعة الكبرى',
                'start_date' => now()->toDateString(),
                'status' => 'active',
                'supervisor_ids' => [$supervisor->id],
                'student_ids' => [$existingStudent->id],
                'new_students' => [
                    [
                        'name' => 'طالب جديد',
                        'phone' => '0599000000',
                        'gender' => 'male',
                        'guardian_name' => 'ولي الطالب',
                        'classroom_id' => $classroom->id,
                        'section_id' => $section->id,
                    ],
                ],
            ])
            ->assertRedirect(route('super-admin.sharia-courses.index', ['mosque_id' => $mosque->id]))
            ->assertSessionHasNoErrors();

        $course = ShariaCourse::withoutGlobalScope('tenant')
            ->where('name', 'دورة الفقه المركزي')
            ->firstOrFail();

        $this->assertSame($mosque->id, $course->tenant_id);
        $this->assertSame(ShariaCourse::SOURCE_SUPER_ADMIN, $course->source);
        $this->assertTrue($course->isFromSuperAdmin());
        $this->assertSame($superAdmin->id, $course->created_by);
        $this->assertSame(1, $course->supervisors()->count());

        $this->assertDatabaseHas('sharia_course_supervisor', [
            'course_id' => $course->id,
            'teacher_id' => $supervisor->id,
        ]);

        $this->assertSame(2, $course->students()->count());
        $this->assertDatabaseHas('sharia_course_students', [
            'course_id' => $course->id,
            'student_id' => $existingStudent->id,
            'name' => 'طالب موجود',
        ]);

        // الطالب الجديد سُجّل في الجامع فعلياً (سجل طالب عادي) ثم رُبط بالدورة.
        $newStudent = Student::withoutGlobalScopes(['tenant', 'study_session'])
            ->where('tenant_id', $mosque->id)
            ->where('name', 'طالب جديد')
            ->firstOrFail();

        $this->assertSame('0599000000', $newStudent->guardian_phone);
        $this->assertSame('ولي الطالب', $newStudent->guardian_name);
        $this->assertSame($classroom->id, $newStudent->classroom_id);
        $this->assertSame($section->id, $newStudent->section_id);

        $this->assertDatabaseHas('sharia_course_students', [
            'course_id' => $course->id,
            'student_id' => $newStudent->id,
            'name' => 'طالب جديد',
            'guardian_name' => 'ولي الطالب',
        ]);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $newStudent->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        // مدير الجامع المستهدف يصله الإشعار، ومدير جامع آخر لا.
        $this->assertSame(1, $manager->fresh()->notifications()->count());
        $this->assertSame('دورة شرعية جديدة', $manager->fresh()->notifications()->first()->data['title']);
        $this->assertSame(0, $otherManager->fresh()->notifications()->count());

        // الشاشات المركزية تُعرض لمدير المساجد.
        $this->actingAs($superAdmin)
            ->get(route('super-admin.sharia-courses.index'))
            ->assertOk()
            ->assertSee('دورة الفقه المركزي')
            ->assertSee('من مدير المساجد');

        $this->actingAs($superAdmin)
            ->get(route('super-admin.sharia-courses.create'))
            ->assertOk()
            ->assertSee('خصائص الدورة');

        // الدورة تظهر في شاشات إدارة الجامع المستهدف فقط.
        config(['app.current_tenant_id' => $mosque->id]);

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.index'))
            ->assertOk()
            ->assertSee('دورة الفقه المركزي')
            ->assertSee('من مدير المساجد');

        $this->actingAs($manager)
            ->get(route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'students']))
            ->assertOk()
            ->assertSee('طالب موجود')
            ->assertSee('طالب جديد');
    }

    public function test_options_endpoint_lists_only_the_selected_mosque_teachers_and_students(): void
    {
        [$mosque] = $this->mosque();
        [, $teacher] = $this->makeTeacher($mosque->id, 'مشرف الجامع المستهدف');
        Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'طالب الجامع المستهدف']);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف الجامع المستهدف']);
        Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'شعبة الجامع المستهدف']);

        [$otherMosque] = $this->mosque();
        $this->makeTeacher($otherMosque->id, 'مشرف جامع آخر');
        Student::factory()->create(['tenant_id' => $otherMosque->id, 'name' => 'طالب جامع آخر']);
        Classroom::create(['tenant_id' => $otherMosque->id, 'name' => 'صف جامع آخر']);

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->getJson(route('super-admin.sharia-courses.options', ['mosque_id' => $mosque->id]))
            ->assertOk()
            ->assertJsonFragment(['name' => 'مشرف الجامع المستهدف'])
            ->assertJsonFragment(['name' => 'طالب الجامع المستهدف'])
            ->assertJsonFragment(['name' => 'صف الجامع المستهدف'])
            ->assertJsonFragment(['name' => 'شعبة الجامع المستهدف'])
            ->assertJsonMissing(['name' => 'مشرف جامع آخر'])
            ->assertJsonMissing(['name' => 'طالب جامع آخر'])
            ->assertJsonMissing(['name' => 'صف جامع آخر']);
    }

    public function test_admin_enrolls_existing_students_once_without_duplicates(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة الحديث',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $first = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'أول']);
        $second = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'ثاني']);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.existing', $course), [
                'student_ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, ShariaCourseStudent::query()->count());
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $first->id, 'name' => 'أول']);
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $second->id, 'name' => 'ثاني']);

        // إعادة التسجيل لا تكرر الصفوف.
        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.existing', $course), [
                'student_ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertSame(2, ShariaCourseStudent::query()->count());
    }

    public function test_admin_adds_a_whole_classroom_or_a_single_section_in_one_click(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة الصف الكامل',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'الصف الأول']);
        $sectionA = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'أ']);
        $sectionB = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'ب']);

        $studentA = Student::factory()->create([
            'tenant_id' => $mosque->id, 'name' => 'طالب أ', 'classroom_id' => $classroom->id, 'section_id' => $sectionA->id,
        ]);
        $studentB = Student::factory()->create([
            'tenant_id' => $mosque->id, 'name' => 'طالب ب', 'classroom_id' => $classroom->id, 'section_id' => $sectionB->id,
        ]);

        // الصف كامل دفعة واحدة.
        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.classroom', $course), ['classroom_id' => $classroom->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ShariaCourseStudent::query()->count());
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $studentA->id]);
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $studentB->id]);

        // إعادة الإرسال لا تكرر الطلاب.
        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.classroom', $course), ['classroom_id' => $classroom->id])
            ->assertRedirect();
        $this->assertSame(2, ShariaCourseStudent::query()->count());

        // شعبة واحدة فقط.
        $course->students()->delete();

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.classroom', $course), ['section_id' => $sectionA->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ShariaCourseStudent::query()->count());
        $this->assertDatabaseHas('sharia_course_students', ['student_id' => $studentA->id]);
        $this->assertDatabaseMissing('sharia_course_students', ['student_id' => $studentB->id]);
    }

    public function test_admin_registers_a_new_student_as_a_real_student_linked_to_the_course(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة التسجيل الجديد',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $classroom = Classroom::create(['tenant_id' => $mosque->id, 'name' => 'صف التسجيل']);
        $section = Section::create(['tenant_id' => $mosque->id, 'classroom_id' => $classroom->id, 'name' => 'شعبة التسجيل']);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.store', $course), [
                'name' => 'طالب جديد رسمي',
                'gender' => 'male',
                'birth_date' => '2012-05-01',
                'guardian_name' => 'أبو الطالب',
                'guardian_phone' => '0599111222',
                'classroom_id' => $classroom->id,
                'section_id' => $section->id,
                'notes' => 'ملاحظة',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student = Student::query()->where('name', 'طالب جديد رسمي')->firstOrFail();

        $this->assertSame($mosque->id, $student->tenant_id);
        $this->assertSame('male', $student->gender);
        $this->assertSame('أبو الطالب', $student->guardian_name);
        $this->assertSame($classroom->id, $student->classroom_id);
        $this->assertSame($section->id, $student->section_id);

        $this->assertDatabaseHas('sharia_course_students', [
            'course_id' => $course->id,
            'student_id' => $student->id,
            'name' => 'طالب جديد رسمي',
        ]);
        $this->assertDatabaseHas('section_students', [
            'student_id' => $student->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
    }

    public function test_new_student_classroom_must_belong_to_the_course_mosque(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة العزل',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        [$otherMosque] = $this->mosque();
        $foreignClassroom = Classroom::create(['tenant_id' => $otherMosque->id, 'name' => 'صف جامع آخر']);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.store', $course), [
                'name' => 'طالب مرفوض',
                'gender' => 'male',
                'classroom_id' => $foreignClassroom->id,
            ])
            ->assertSessionHasErrors('classroom_id');

        $this->assertSame(0, Student::query()->count());
        $this->assertSame(0, ShariaCourseStudent::query()->count());
    }

    public function test_updating_a_linked_course_student_syncs_the_official_student_record(): void
    {
        [$mosque, $admin] = $this->mosque();
        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة المزامنة',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $student = Student::factory()->create(['tenant_id' => $mosque->id, 'name' => 'الاسم القديم']);

        $this->actingAs($admin)
            ->post(route('admin.sharia-courses.students.existing', $course), ['student_ids' => [$student->id]])
            ->assertRedirect();

        $enrolled = ShariaCourseStudent::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.sharia-courses.students.update', $enrolled), [
                'name' => 'الاسم المحدّث',
                'gender' => 'female',
                'guardian_name' => 'ولي محدّث',
                'guardian_phone' => '0599000111',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('الاسم المحدّث', $student->name);
        $this->assertSame('female', $student->gender);
        $this->assertSame('ولي محدّث', $student->guardian_name);
        $this->assertSame('0599000111', $student->guardian_phone);
        $this->assertSame('الاسم المحدّث', $enrolled->fresh()->name);
    }

    public function test_memorization_status_is_updated_by_admin_and_supervising_teacher_only(): void
    {
        [$mosque, $admin] = $this->mosque();
        [$supervisorUser, $supervisor] = $this->makeTeacher($mosque->id, 'المشرف');
        [$otherUser] = $this->makeTeacher($mosque->id, 'معلم غير مشرف');

        $course = ShariaCourse::create([
            'tenant_id' => $mosque->id,
            'name' => 'دورة التجويد',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $course->supervisors()->sync([$supervisor->id]);

        $student = ShariaCourseStudent::create([
            'tenant_id' => $mosque->id,
            'course_id' => $course->id,
            'name' => 'طالب الحفظ',
            'status' => 'active',
        ]);

        // المدير يحدّث الحالة.
        $this->actingAs($admin)
            ->patch(route('admin.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::HalfMemorized->value,
                'memorization_notes' => 'أتمّ نصف المنهج',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(ShariaMemorizationStatus::HalfMemorized, $student->memorization_status);
        $this->assertSame($admin->id, $student->memorization_updated_by);

        // الواجهة تعرض محرّر الحالة الاحترافي مع من حدّثها ومتى.
        $this->actingAs($admin)
            ->get(route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'students']))
            ->assertOk()
            ->assertSee('حالة الحفظ — طالب الحفظ')
            ->assertSee('أتم نصف المقرر')
            ->assertSee('آخر تحديث:')
            ->assertSee($admin->name);

        // المشرف يحدّث الحالة.
        $this->actingAs($supervisorUser)
            ->patch(route('teacher.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::Memorized->value,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(ShariaMemorizationStatus::Memorized, $student->memorization_status);
        $this->assertSame($supervisorUser->id, $student->memorization_updated_by);
        $this->assertNotNull($student->memorization_updated_at);

        $this->actingAs($supervisorUser)
            ->get(route('teacher.sharia-courses.show', ['course' => $course, 'tab' => 'students']))
            ->assertOk()
            ->assertSee('حالة الحفظ — طالب الحفظ')
            ->assertSee('أتم حفظ المقرر كاملاً')
            ->assertSee($supervisorUser->name);

        // معلم غير مشرف لا يستطيع التحديث.
        $this->actingAs($otherUser)
            ->patch(route('teacher.sharia-courses.students.memorization', $student), [
                'memorization_status' => ShariaMemorizationStatus::NotMemorized->value,
            ])
            ->assertForbidden();

        $this->assertSame(ShariaMemorizationStatus::Memorized, $student->fresh()->memorization_status);
    }

    public function test_super_admin_course_is_isolated_from_other_mosques(): void
    {
        [$mosque] = $this->mosque();
        [$otherMosque, $otherManager] = $this->mosque();

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post(route('super-admin.sharia-courses.store'), [
                'mosque_id' => $mosque->id,
                'name' => 'دورة خاصة',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        config(['app.current_tenant_id' => $otherMosque->id]);

        $this->actingAs($otherManager)
            ->get(route('admin.sharia-courses.index'))
            ->assertOk()
            ->assertDontSee('دورة خاصة');
    }
}
