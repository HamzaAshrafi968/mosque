<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'جامع النور']);

        config(['app.current_tenant_id' => $tenant->id]);

        User::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'name' => 'مدير الجامع',
            'email' => 'admin@mosque.test',
            'gender' => 'male',
        ]);

        $teacherUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'الأستاذ أحمد',
            'email' => 'teacher@mosque.test',
            'gender' => 'male',
        ]);

        $teacher = Teacher::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $teacherUser->id,
            'name' => $teacherUser->name,
            'gender' => 'male',
        ]);

        Teacher::factory(4)->create(['tenant_id' => $tenant->id]);

        $classrooms = collect(['الصف الأول', 'الصف الثاني', 'الصف الثالث'])
            ->map(fn ($name) => Classroom::create(['tenant_id' => $tenant->id, 'name' => $name]));

        $classrooms->each(function (Classroom $classroom) use ($tenant) {
            foreach (['أ', 'ب'] as $sectionName) {
                Section::create([
                    'tenant_id' => $tenant->id,
                    'classroom_id' => $classroom->id,
                    'name' => $sectionName,
                ]);
            }
        });

        $sections = Section::with('classroom')->get();

        Student::factory(60)->make(['tenant_id' => $tenant->id])->each(function (Student $student) use ($sections) {
            $section = $sections->random();
            $student->classroom_id = $section->classroom_id;
            $student->section_id = $section->id;
            $student->save();
        });

        Subject::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacher->id,
            'name' => 'القرآن الكريم',
            'weekly_lessons' => 5,
        ]);

        Subject::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacher->id,
            'name' => 'التجويد',
            'weekly_lessons' => 3,
        ]);

        $this->call(QuranDataSeeder::class);
    }
}
