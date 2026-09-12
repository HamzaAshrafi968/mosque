<?php

namespace Tests\Feature;

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
        return \App\Models\User::where('email', 'admin@mosque.test')->firstOrFail();
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

        $student = \App\Models\Student::first();
        $this->get(route('admin.students.show', $student))->assertOk();
        $this->get(route('admin.students.edit', $student))->assertOk();
    }

    public function test_student_photo_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');

        $student = \App\Models\Student::first();

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
}
