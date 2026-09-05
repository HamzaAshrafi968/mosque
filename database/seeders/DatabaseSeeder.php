<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Section;
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

        config(['app.current_tenant_id' => null]);

        $this->call(QuranDataSeeder::class);
    }
}
