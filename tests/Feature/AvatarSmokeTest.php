<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin()
    {
        return User::where('email', 'admin@mosque.test')->firstOrFail();
    }

    public function test_admin_avatar_pages_render(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('admin.students.index'))->assertOk();
        $this->get(route('admin.teachers.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.parents.index'))->assertOk();
        $this->get(route('admin.teachers.create'))->assertOk();
        $this->get(route('admin.parents.create'))->assertOk();
        $this->get(route('admin.students.create'))->assertOk();

        $student = Student::first();
        $this->get(route('admin.students.show', $student))->assertOk();
        $this->get(route('admin.students.edit', $student))->assertOk();
    }

    public function test_student_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');

        $student = Student::first();

        $this->actingAs($this->admin())
            ->patch(route('admin.students.update', $student), [
                'name' => $student->name,
                'gender' => $student->gender,
                'photo' => UploadedFile::fake()->image('avatar.jpg', 120, 120),
            ])
            ->assertSessionHas('success');

        $this->assertNotNull($student->fresh()->photo);

        $this->actingAs($this->admin())
            ->patch(route('admin.students.update', $student), [
                'name' => $student->name,
                'gender' => $student->gender,
                'remove_photo' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertNull($student->fresh()->photo);
    }

    public function test_admin_user_can_set_own_photo(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'admin',
                'photo' => UploadedFile::fake()->image('manager.jpg'),
            ])
            ->assertSessionHas('success');

        $this->assertNotNull($admin->fresh()->photo);
    }

    public function test_teacher_user_photo_syncs_to_teacher_profile(): void
    {
        Storage::fake('public');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'أستاذ جديد',
                'email' => 'newteacher@mosque.test',
                'password' => 'password123',
                'role' => 'teacher',
                'gender' => 'male',
                'photo' => UploadedFile::fake()->image('teacher.jpg'),
            ])
            ->assertSessionHas('success');

        $user = User::where('email', 'newteacher@mosque.test')->firstOrFail();
        $teacher = $user->teacher()->firstOrFail();

        $this->assertNotNull($user->photo);
        $this->assertSame($user->photo, $teacher->photo);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'أستاذ جديد',
                'email' => 'newteacher@mosque.test',
                'role' => 'teacher',
                'photo' => UploadedFile::fake()->image('teacher2.jpg'),
            ])
            ->assertSessionHas('success');

        $this->assertSame($user->fresh()->photo, $teacher->fresh()->photo);
    }

    public function test_student_portal_account_created_later_inherits_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.students.store'), [
                'name' => 'طالب بالصورة',
                'gender' => 'male',
                'photo' => UploadedFile::fake()->image('student.jpg'),
            ])
            ->assertSessionHas('success');

        $student = Student::where('name', 'طالب بالصورة')->firstOrFail();
        $this->assertNotNull($student->photo);
        $this->assertNull($student->user_id);

        $this->actingAs($this->admin())
            ->patch(route('admin.students.update', $student), [
                'name' => 'طالب بالصورة',
                'gender' => 'male',
                'portal_account_present' => '1',
                'portal_email' => 'late.student@mosque.test',
                'portal_password' => 'password123',
            ])
            ->assertSessionHas('success');

        $this->assertSame($student->photo, $student->fresh()->user->photo);
    }

    public function test_guardian_portal_account_created_later_inherits_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.parents.store'), [
                'name' => 'ولي أمر بالصورة',
                'photo' => UploadedFile::fake()->image('guardian.jpg'),
            ])
            ->assertSessionHas('success');

        $guardian = Guardian::where('name', 'ولي أمر بالصورة')->firstOrFail();
        $this->assertNotNull($guardian->photo);
        $this->assertNull($guardian->user_id);

        $this->actingAs($this->admin())
            ->patch(route('admin.parents.update', $guardian), [
                'name' => 'ولي أمر بالصورة',
                'email' => 'late.guardian@mosque.test',
                'password' => 'password123',
            ])
            ->assertSessionHas('success');

        $this->assertSame($guardian->photo, $guardian->fresh()->user->photo);
    }
}
