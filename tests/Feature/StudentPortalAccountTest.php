<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPortalAccountTest extends TestCase
{
    private function adminWithMosque(): User
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        return User::factory()->admin()->for($tenant)->create();
    }

    private function studentWithAccount(User $admin, string $email = 'student@mosque.test', string $password = 'old-password'): array
    {
        $student = Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'الطالب']);

        $user = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'الطالب',
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_STUDENT,
        ]);

        $student->update(['user_id' => $user->id]);

        return [$student, $user];
    }

    public function test_email_can_be_changed_alone_without_a_password(): void
    {
        $admin = $this->adminWithMosque();
        [$student, $user] = $this->studentWithAccount($admin);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => 'new@mosque.test',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('new@mosque.test', $user->fresh()->email);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_can_be_changed_alone_without_touching_the_email(): void
    {
        $admin = $this->adminWithMosque();
        [$student, $user] = $this->studentWithAccount($admin);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => 'student@mosque.test',
            'portal_password' => 'new-password',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('student@mosque.test', $user->fresh()->email);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_partial_update_without_portal_fields_keeps_the_account(): void
    {
        $admin = $this->adminWithMosque();
        [$student, $user] = $this->studentWithAccount($admin);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب المعدل',
            'gender' => 'male',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame($user->id, $student->fresh()->user_id);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_clearing_the_email_revokes_the_account(): void
    {
        $admin = $this->adminWithMosque();
        [$student, $user] = $this->studentWithAccount($admin);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => '',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertNull($student->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_email_change_to_a_taken_email_is_rejected(): void
    {
        $admin = $this->adminWithMosque();
        [$student] = $this->studentWithAccount($admin);

        User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'email' => 'taken@mosque.test',
            'role' => User::ROLE_STUDENT,
        ]);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => 'taken@mosque.test',
        ])->assertSessionHasErrors('portal_email');

        $this->assertSame('student@mosque.test', $student->fresh()->user->email);
    }

    public function test_email_without_password_for_a_new_account_is_rejected(): void
    {
        $admin = $this->adminWithMosque();
        $student = Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'الطالب']);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => 'student@mosque.test',
        ])->assertSessionHasErrors('portal_password');

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_new_account_is_created_with_email_and_password(): void
    {
        $admin = $this->adminWithMosque();
        $student = Student::factory()->create(['tenant_id' => $admin->tenant_id, 'name' => 'الطالب']);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'male',
            'portal_account_present' => 1,
            'portal_email' => 'student@mosque.test',
            'portal_password' => 'secret-password',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertNotNull($student->fresh()->user_id);
        $this->assertTrue(Hash::check('secret-password', $student->fresh()->user->password));
    }

    public function test_editing_the_profile_does_not_touch_the_account_even_with_submitted_portal_values(): void
    {
        $admin = $this->adminWithMosque();
        [$student, $user] = $this->studentWithAccount($admin);

        User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'email' => 'someone.else@mosque.test',
            'role' => User::ROLE_STUDENT,
        ]);

        // Browser autofill can fill the portal fields while the manager only
        // edits profile data; without the explicit flag they must be ignored.
        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'name' => 'الطالب',
            'gender' => 'female',
            'portal_email' => 'someone.else@mosque.test',
            'portal_password' => 'autofilled-password',
        ])->assertRedirect(route('admin.students.show', $student));

        $student->refresh();
        $user->refresh();

        $this->assertSame('female', $student->gender);
        $this->assertSame($user->id, $student->user_id);
        $this->assertSame('student@mosque.test', $user->email);
        $this->assertTrue(Hash::check('old-password', $user->password));
    }
}
