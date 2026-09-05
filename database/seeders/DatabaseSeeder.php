<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = app(RoleService::class);

        // ---- مدير الجوامع (global, above all mosques) ----
        $roles->ensureGlobalSuperAdminRole();

        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'مدير الجوامع',
            'email' => 'super@mosque.test',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'gender' => 'male',
        ]);

        $roles->assignRole($superAdmin, RoleService::ROLE_SUPER_ADMIN);

        // ---- Mosque 1: جامع النور (full demo data) ----
        $mosque1 = Tenant::factory()->create([
            'name' => 'جامع النور',
            'code' => 'NUR',
            'email' => 'info@alnoor.mosque',
            'address' => 'الرياض - حي النور',
        ]);

        config(['app.current_tenant_id' => $mosque1->id]);
        $roles->provisionTenantRoles($mosque1);

        $manager = User::factory()->admin()->create([
            'tenant_id' => $mosque1->id,
            'name' => 'مدير الجامع - جامع النور',
            'email' => 'admin@mosque.test',
            'gender' => 'male',
        ]);

        $teacherUser = User::factory()->create([
            'tenant_id' => $mosque1->id,
            'name' => 'الأستاذ أحمد',
            'email' => 'teacher@mosque.test',
            'gender' => 'male',
        ]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $mosque1->id,
            'user_id' => $teacherUser->id,
            'name' => $teacherUser->name,
            'gender' => 'male',
        ]);

        Teacher::factory(4)->create(['tenant_id' => $mosque1->id]);

        $classrooms = collect(['الصف الأول', 'الصف الثاني', 'الصف الثالث'])
            ->map(fn ($name) => Classroom::create(['tenant_id' => $mosque1->id, 'name' => $name]));

        $classrooms->each(function (Classroom $classroom) use ($mosque1) {
            foreach (['أ', 'ب'] as $sectionName) {
                Section::create([
                    'tenant_id' => $mosque1->id,
                    'classroom_id' => $classroom->id,
                    'name' => $sectionName,
                ]);
            }
        });

        $sections = Section::where('tenant_id', $mosque1->id)->with('classroom')->get();

        Student::factory(60)->make(['tenant_id' => $mosque1->id])->each(function (Student $student) use ($sections) {
            $section = $sections->random();
            $student->classroom_id = $section->classroom_id;
            $student->section_id = $section->id;
            $student->save();
        });

        $today = now()->toDateString();

        // Enrollments preserve each student's membership history (spec §6).
        Student::where('tenant_id', $mosque1->id)->each(function (Student $student) use ($today) {
            SectionStudent::create([
                'tenant_id' => $student->tenant_id,
                'section_id' => $student->section_id,
                'student_id' => $student->id,
                'status' => 'active',
                'enrolled_at' => $today,
            ]);
        });

        // Assign the demo teacher to sections so section-scoped access works.
        $sections->take(4)->each(function (Section $section) use ($teacher) {
            SectionTeacher::create([
                'tenant_id' => $teacher->tenant_id,
                'section_id' => $section->id,
                'teacher_id' => $teacher->id,
                'role' => 'lead',
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ]);
        });

        Subject::create([
            'tenant_id' => $mosque1->id,
            'teacher_id' => $teacher->id,
            'name' => 'القرآن الكريم',
            'weekly_lessons' => 5,
        ]);

        Subject::create([
            'tenant_id' => $mosque1->id,
            'teacher_id' => $teacher->id,
            'name' => 'التجويد',
            'weekly_lessons' => 3,
        ]);

        // ---- Portals demo data (parent + student accounts) ----
        $children = Student::where('tenant_id', $mosque1->id)->orderBy('name')->limit(2)->get();
        $childA = $children->first();
        $childB = $children->last();

        $guardianUser = User::create([
            'tenant_id' => $mosque1->id,
            'name' => 'أبو محمد',
            'email' => 'parent@mosque.test',
            'password' => 'password',
            'role' => User::ROLE_GUARDIAN,
            'phone' => '0500000001',
            'gender' => 'male',
        ]);

        $guardian = Guardian::create([
            'tenant_id' => $mosque1->id,
            'user_id' => $guardianUser->id,
            'name' => 'أبو محمد',
            'phone' => '0500000001',
            'email' => 'parent@mosque.test',
        ]);

        ParentStudent::create([
            'tenant_id' => $mosque1->id,
            'parent_id' => $guardian->id,
            'student_id' => $childA->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        ParentStudent::create([
            'tenant_id' => $mosque1->id,
            'parent_id' => $guardian->id,
            'student_id' => $childB->id,
            'relationship' => 'father',
        ]);

        $studentUser = User::create([
            'tenant_id' => $mosque1->id,
            'name' => $childA->name,
            'email' => 'student@mosque.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT,
            'gender' => 'male',
        ]);

        $childA->update(['user_id' => $studentUser->id]);

        // Grant the demo sheikh role finance permissions (own scope) so the
        // teacher@mosque.test account can exercise the cash ledger (spec §32).
        $teacherRole = Role::where('tenant_id', $mosque1->id)->where('code', 'teacher')->first();

        if ($teacherRole) {
            foreach (['finance.view', 'finance.create', 'finance.adjust', 'finance.transfer'] as $code) {
                $permission = Permission::where('code', $code)->first();

                if ($permission && ! $teacherRole->permissions()->where('permissions.code', $code)->exists()) {
                    $teacherRole->permissions()->attach($permission->id, ['scope' => 'own']);
                }
            }
        }

        // ---- Mosque 2: جامع الفرقان (isolation demo) ----
        $mosque2 = Tenant::factory()->create([
            'name' => 'جامع الفرقان',
            'code' => 'FUR',
            'email' => 'info@alfurqan.mosque',
            'address' => 'جدة - حي الفرقان',
        ]);

        config(['app.current_tenant_id' => $mosque2->id]);
        $roles->provisionTenantRoles($mosque2);

        User::factory()->admin()->create([
            'tenant_id' => $mosque2->id,
            'name' => 'مدير الجامع - جامع الفرقان',
            'email' => 'admin2@mosque.test',
            'gender' => 'male',
        ]);

        $mosque2TeacherUser = User::factory()->create([
            'tenant_id' => $mosque2->id,
            'name' => 'الأستاذ خالد',
            'email' => 'teacher2@mosque.test',
            'gender' => 'male',
        ]);

        Teacher::factory()->create([
            'tenant_id' => $mosque2->id,
            'user_id' => $mosque2TeacherUser->id,
            'name' => $mosque2TeacherUser->name,
            'gender' => 'male',
        ]);

        $classrooms2 = collect(['الصف الأول', 'الصف الثاني'])
            ->map(fn ($name) => Classroom::create(['tenant_id' => $mosque2->id, 'name' => $name]));

        $sections2 = $classrooms2->flatMap(fn (Classroom $classroom) => collect([
            Section::create(['tenant_id' => $mosque2->id, 'classroom_id' => $classroom->id, 'name' => 'أ']),
            Section::create(['tenant_id' => $mosque2->id, 'classroom_id' => $classroom->id, 'name' => 'ب']),
        ]));

        Student::factory(25)->make(['tenant_id' => $mosque2->id])->each(function (Student $student) use ($sections2) {
            $section = $sections2->random();
            $student->classroom_id = $section->classroom_id;
            $student->section_id = $section->id;
            $student->save();
        });

        $mosque2Teacher = Teacher::where('tenant_id', $mosque2->id)->where('user_id', $mosque2TeacherUser->id)->first();

        Student::where('tenant_id', $mosque2->id)->each(function (Student $student) use ($today) {
            SectionStudent::create([
                'tenant_id' => $student->tenant_id,
                'section_id' => $student->section_id,
                'student_id' => $student->id,
                'status' => 'active',
                'enrolled_at' => $today,
            ]);
        });

        $sections2->take(3)->each(function (Section $section) use ($mosque2Teacher) {
            SectionTeacher::create([
                'tenant_id' => $mosque2Teacher->tenant_id,
                'section_id' => $section->id,
                'teacher_id' => $mosque2Teacher->id,
                'role' => 'lead',
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ]);
        });

        config(['app.current_tenant_id' => null]);

        $this->call(QuranDataSeeder::class);
    }
}
