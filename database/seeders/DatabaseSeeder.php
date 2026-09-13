<?php

namespace Database\Seeders;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\IjazahMonthlyEvaluation;
use App\Models\IjazahWeeklyEvaluation;
use App\Models\ParentStudent;
use App\Models\Program;
use App\Models\ProgramAttribute;
use App\Models\ProgramEnrollment;
use App\Models\ProgramPeriod;
use App\Models\QualifyingWeeklyEvaluation;
use App\Models\QuranRecitationSession;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\SectionTeacher;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseAttendance;
use App\Models\ShariaCourseLesson;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\QuranProgramService;
use App\Services\RoleService;
use App\Services\StudySessionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

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
        $sessions = app(StudySessionService::class);
        $sessions->provisionTenantSessions($mosque1);
        app(ProgramService::class)->provisionTenantPrograms($mosque1);

        [$firstSession, $secondSession] = StudySession::where('tenant_id', $mosque1->id)->orderBy('name')->get();

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
            'study_session_id' => $firstSession->id,
        ]);

        Teacher::factory(2)->create(['tenant_id' => $mosque1->id, 'study_session_id' => $firstSession->id]);
        Teacher::factory(2)->create(['tenant_id' => $mosque1->id, 'study_session_id' => $secondSession->id]);

        $classrooms = collect(['الصف الأول', 'الصف الثاني', 'الصف الثالث'])
            ->map(fn ($name) => Classroom::create(['tenant_id' => $mosque1->id, 'name' => $name]));

        $sessionFor = fn (string $sectionName) => $sectionName === 'أ' ? $firstSession->id : $secondSession->id;

        $classrooms->each(function (Classroom $classroom) use ($mosque1, $sessionFor) {
            foreach (['أ', 'ب'] as $sectionName) {
                Section::create([
                    'tenant_id' => $mosque1->id,
                    'classroom_id' => $classroom->id,
                    'name' => $sectionName,
                    'study_session_id' => $sessionFor($sectionName),
                ]);
            }
        });

        $sections = Section::where('tenant_id', $mosque1->id)->with('classroom')->get();

        Student::factory(60)->make(['tenant_id' => $mosque1->id])->each(function (Student $student) use ($sections) {
            $section = $sections->random();
            $student->classroom_id = $section->classroom_id;
            $student->section_id = $section->id;
            $student->study_session_id = $section->study_session_id;
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

        // Assign the demo teacher to the first-session sections so section-scoped access works.
        $sections->where('study_session_id', $firstSession->id)->each(function (Section $section) use ($teacher) {
            SectionTeacher::create([
                'tenant_id' => $teacher->tenant_id,
                'section_id' => $section->id,
                'teacher_id' => $teacher->id,
                'role' => 'lead',
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ]);
        });

        // ---- Quran programs demo data (spec: mosque_management_quran_programs.md) ----
        $quranStudents = Student::where('tenant_id', $mosque1->id)->orderBy('name')->take(10)->get();

        $quranStudents->take(6)->each(function (Student $student) use ($teacher, $today) {
            QuranRecitationSession::create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'type' => fake()->boolean(70) ? 'new' : 'revision',
                'date' => $today,
                'amount' => fake()->randomElement([1, 2, 3, 5, 10]),
                'recited_portion' => 'جزء '.Arr::random(['عم', 'تبارك', 'قد سمع', 'يس']),
                'result' => fake()->randomElement(['excellent', 'very_good', 'good', 'needs_review']),
            ]);
        });

        // Two hafiz with the full automatic journey (2 already moved to ijazah).
        $quranPrograms = app(QuranProgramService::class);

        $quranStudents->splice(6)->take(2)->values()->each(function (Student $student, $index) use ($quranPrograms, $manager) {
            $completion = $quranPrograms->recordCompletion($student, now()->subMonths($index)->toDateString(), null, $manager);
            $quranPrograms->confirmCompletion($completion, $manager);

            foreach (range(1, 4) as $week) {
                QualifyingWeeklyEvaluation::create([
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'week_start' => now()->subWeeks(4 - $week)->startOfWeek()->toDateString(),
                    'week_end' => now()->subWeeks(4 - $week)->endOfWeek()->toDateString(),
                    'amount' => 5,
                    'recited_portion' => 'الأجزاء ١-٥',
                    'result' => 'passed',
                ]);
            }

            if ($index === 0) {
                $enrollment = $quranPrograms->activeEnrollment($student, ProgramType::Qualifying);
                $quranPrograms->completeQualifying($enrollment, $manager);
            }
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

        // ---- تخصصات الجداول: فترات وجداول أسبوعية تجريبية للبرامج الخمسة (demo) ----
        $programs = Program::where('tenant_id', $mosque1->id)->get()->keyBy('code');

        $tahfeez = $programs->get('tahfeez');

        if ($tahfeez) {
            ProgramAttribute::create([
                'tenant_id' => $mosque1->id,
                'program_id' => $tahfeez->id,
                'name' => 'عدد الأجزاء الأسبوعية',
                'field_key' => 'weekly_juz',
                'field_type' => 'number',
                'value' => '5',
                'sort_order' => 0,
            ]);

            ProgramAttribute::create([
                'tenant_id' => $mosque1->id,
                'program_id' => $tahfeez->id,
                'name' => 'المستوى',
                'field_key' => 'level',
                'field_type' => 'select',
                'options' => ['مبتدئ', 'متوسط', 'متقدم'],
                'value' => 'متوسط',
                'sort_order' => 1,
            ]);
        }

        $otherFirstSessionTeachers = Teacher::where('tenant_id', $mosque1->id)
            ->where('study_session_id', $firstSession->id)
            ->whereKeyNot($teacher->id)
            ->orderBy('name')
            ->get();

        $secondSessionTeachers = Teacher::where('tenant_id', $mosque1->id)
            ->where('study_session_id', $secondSession->id)
            ->orderBy('name')
            ->get();

        $quranSubjectId = Subject::where('tenant_id', $mosque1->id)->where('name', 'القرآن الكريم')->value('id');

        // كل برنامج بفترته وجدوله الأسبوعي: التحفيظ (الفترة الأولى والثانية)،
        // الإجازة، اختبارات الحفظ، الدورات الشرعية، البرامج القرآنية.
        $demoPrograms = [
            'tahfeez' => [
                'periods' => [['الفترة الأولى', '06:30', '07:30'], ['الفترة الثانية', '07:30', '08:30']],
                'teacher' => $teacher,
                'classroom' => $classrooms[0],
                'session' => $firstSession,
                'subject_id' => $quranSubjectId,
            ],
            'ijazah' => [
                'periods' => [['الفترة الأولى', '09:00', '10:00']],
                'teacher' => $otherFirstSessionTeachers->get(0) ?? $teacher,
                'classroom' => $classrooms[1],
                'session' => $firstSession,
                'subject_id' => null,
            ],
            'hafiz_exams' => [
                'periods' => [['الفترة الأولى', '10:00', '11:00']],
                'teacher' => $otherFirstSessionTeachers->get(1) ?? $teacher,
                'classroom' => $classrooms[2],
                'session' => $firstSession,
                'subject_id' => null,
            ],
            'sharia_courses' => [
                'periods' => [['الفترة الأولى', '16:00', '17:00']],
                'teacher' => $secondSessionTeachers->get(0) ?? $teacher,
                'classroom' => $classrooms[0],
                'session' => $secondSession,
                'subject_id' => null,
            ],
            'quran' => [
                'periods' => [['الفترة الأولى', '17:00', '18:00']],
                'teacher' => $secondSessionTeachers->get(1) ?? $teacher,
                'classroom' => $classrooms[1],
                'session' => $secondSession,
                'subject_id' => null,
            ],
        ];

        foreach ($demoPrograms as $code => $definition) {
            $program = $programs->get($code);

            if (! $program) {
                continue;
            }

            $periods = collect($definition['periods'])->map(fn (array $row, int $index) => ProgramPeriod::create([
                'tenant_id' => $mosque1->id,
                'program_id' => $program->id,
                'name' => $row[0],
                'starts_at' => $row[1],
                'ends_at' => $row[2],
                'sort_order' => $index,
            ]));

            $firstPeriod = $periods->first();

            // جدول أسبوعي (الأحد–الخميس) للفترة الأولى من كل برنامج.
            foreach (range(0, 4) as $day) {
                Schedule::create([
                    'tenant_id' => $mosque1->id,
                    'classroom_id' => $definition['classroom']->id,
                    'subject_id' => $definition['subject_id'],
                    'teacher_id' => $definition['teacher']->id,
                    'program_id' => $program->id,
                    'program_period_id' => $firstPeriod->id,
                    'study_session_id' => $definition['session']->id,
                    'day_of_week' => $day,
                    'starts_at' => $firstPeriod->starts_at,
                    'ends_at' => $firstPeriod->ends_at,
                ]);
            }
        }

        // ---- ساعات عمل المشرفين (spec: work_hours_sharia_courses_quran_pages.md §1) ----
        $workHourPeriods = [
            [0, '07:30', '12:00', 'الفترة الصباحية'],
            [0, '16:00', '18:30', 'حلقة الحفظ'],
            [1, '07:30', '12:00', null],
            [2, '07:30', '12:00', null],
            [3, '07:30', '12:00', null],
            [4, '07:30', '10:30', 'مراجعة عامة'],
        ];

        foreach ($workHourPeriods as [$day, $start, $end, $notes]) {
            TeacherWorkHour::create([
                'tenant_id' => $mosque1->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'notes' => $notes,
                'created_by' => $manager->id,
            ]);
        }

        // ---- أسابيع برنامج الإجازة (spec §3) ----
        $ijazahStudent = ProgramEnrollment::query()
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->first()?->student;

        if ($ijazahStudent) {
            $currentMonth = now()->format('Y-m');
            $monthStart = now()->startOfMonth();

            foreach (range(1, 4) as $week) {
                IjazahWeeklyEvaluation::create([
                    'tenant_id' => $mosque1->id,
                    'student_id' => $ijazahStudent->id,
                    'month' => $currentMonth,
                    'week' => $week,
                    'week_start' => $monthStart->copy()->addWeeks($week - 1)->toDateString(),
                    'week_end' => $monthStart->copy()->addWeeks($week - 1)->addDays(6)->toDateString(),
                    'amount' => 5,
                    'recited_portion' => 'مقدار الأسبوع '.$week,
                    'result' => $week === 4 ? 'needs_review' : 'passed',
                    'evaluated_by' => $teacher->id,
                ]);
            }

            IjazahMonthlyEvaluation::create([
                'tenant_id' => $mosque1->id,
                'student_id' => $ijazahStudent->id,
                'month' => $currentMonth,
                'amount' => 20,
                'recited_portion' => 'من الجزء ١ إلى الجزء ٤',
                'result' => 'passed',
                'evaluated_by' => $teacher->id,
            ]);
        }

        // ---- الدورة الشرعية (spec §4) ----
        $shariaCourse = ShariaCourse::create([
            'tenant_id' => $mosque1->id,
            'name' => 'دورة أحكام الصلاة',
            'description' => 'دورة شرعية أسبوعية في أحكام الصلاة والطهارة',
            'supervisor_id' => $teacher->id,
            'location' => 'القاعة الكبرى',
            'start_date' => now()->startOfMonth()->toDateString(),
            'status' => 'active',
            'created_by' => $manager->id,
        ]);

        $shariaLessons = collect([
            ['الطهارة وأحكام المياه', 'lesson', now()->toDateString(), '17:00', '18:00'],
            ['محاضرة: فضل العلم وأهله', 'lecture', now()->addDays(7)->toDateString(), '19:00', '20:00'],
        ])->map(fn ($row) => ShariaCourseLesson::create([
            'tenant_id' => $mosque1->id,
            'course_id' => $shariaCourse->id,
            'title' => $row[0],
            'type' => $row[1],
            'date' => $row[2],
            'start_time' => $row[3],
            'end_time' => $row[4],
            'teacher_id' => $teacher->id,
        ]));

        $shariaStudents = collect(['سعد بن أبي وقاص', 'عبد الرحمن بن عوف', 'معاذ بن جبل', 'أبو عبيدة'])
            ->map(fn (string $name, int $index) => ShariaCourseStudent::create([
                'tenant_id' => $mosque1->id,
                'course_id' => $shariaCourse->id,
                'name' => $name,
                'phone' => '05500000'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'gender' => 'male',
                'status' => 'active',
            ]));

        $shariaStatuses = ['present', 'present', 'absent', 'late'];
        $shariaStudents->each(function (ShariaCourseStudent $student, int $index) use ($mosque1, $shariaCourse, $shariaLessons, $teacher, $shariaStatuses) {
            ShariaCourseAttendance::create([
                'tenant_id' => $mosque1->id,
                'course_id' => $shariaCourse->id,
                'lesson_id' => $shariaLessons->first()->id,
                'student_id' => $student->id,
                'date' => $shariaLessons->first()->date->toDateString(),
                'status' => $shariaStatuses[$index] ?? 'present',
                'recorded_by' => $teacher->user_id,
            ]);
        });

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

        // ---- Mosque 2: جامع الفرقان (isolation demo) ----
        $mosque2 = Tenant::factory()->create([
            'name' => 'جامع الفرقان',
            'code' => 'FUR',
            'email' => 'info@alfurqan.mosque',
            'address' => 'جدة - حي الفرقان',
        ]);

        config(['app.current_tenant_id' => $mosque2->id]);
        $roles->provisionTenantRoles($mosque2);
        $sessions->provisionTenantSessions($mosque2);
        app(ProgramService::class)->provisionTenantPrograms($mosque2);

        [$firstSession2, $secondSession2] = StudySession::where('tenant_id', $mosque2->id)->orderBy('name')->get();

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
            'study_session_id' => $firstSession2->id,
        ]);

        $classrooms2 = collect(['الصف الأول', 'الصف الثاني'])
            ->map(fn ($name) => Classroom::create(['tenant_id' => $mosque2->id, 'name' => $name]));

        $sessionFor2 = fn (string $sectionName) => $sectionName === 'أ' ? $firstSession2->id : $secondSession2->id;

        $sections2 = $classrooms2->flatMap(fn (Classroom $classroom) => collect([
            Section::create(['tenant_id' => $mosque2->id, 'classroom_id' => $classroom->id, 'name' => 'أ', 'study_session_id' => $sessionFor2('أ')]),
            Section::create(['tenant_id' => $mosque2->id, 'classroom_id' => $classroom->id, 'name' => 'ب', 'study_session_id' => $sessionFor2('ب')]),
        ]));

        Student::factory(25)->make(['tenant_id' => $mosque2->id])->each(function (Student $student) use ($sections2) {
            $section = $sections2->random();
            $student->classroom_id = $section->classroom_id;
            $student->section_id = $section->id;
            $student->study_session_id = $section->study_session_id;
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

        $sections2->where('study_session_id', $firstSession2->id)->each(function (Section $section) use ($mosque2Teacher) {
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
