<?php

namespace App\Providers;

use App\Contracts\Repositories\AnnouncementRepositoryInterface;
use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Contracts\Repositories\HomeworkRepositoryInterface;
use App\Contracts\Repositories\LessonRepositoryInterface;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\QuranReviewSessionRepositoryInterface;
use App\Contracts\Repositories\RewardPointRepositoryInterface;
use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Contracts\Repositories\StudentRepositoryInterface;
use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\Eloquent\AnnouncementRepository;
use App\Repositories\Eloquent\AttendanceRepository;
use App\Repositories\Eloquent\ClassroomRepository;
use App\Repositories\Eloquent\ExamRepository;
use App\Repositories\Eloquent\GradeRepository;
use App\Repositories\Eloquent\HomeworkRepository;
use App\Repositories\Eloquent\LessonRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\QuranReviewSessionRepository;
use App\Repositories\Eloquent\RewardPointRepository;
use App\Repositories\Eloquent\ScheduleRepository;
use App\Repositories\Eloquent\StudentRepository;
use App\Repositories\Eloquent\SubjectRepository;
use App\Repositories\Eloquent\TeacherRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public array $bindings = [
        StudentRepositoryInterface::class => StudentRepository::class,
        TeacherRepositoryInterface::class => TeacherRepository::class,
        ClassroomRepositoryInterface::class => ClassroomRepository::class,
        SubjectRepositoryInterface::class => SubjectRepository::class,
        ScheduleRepositoryInterface::class => ScheduleRepository::class,
        AttendanceRepositoryInterface::class => AttendanceRepository::class,
        ExamRepositoryInterface::class => ExamRepository::class,
        GradeRepositoryInterface::class => GradeRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        AnnouncementRepositoryInterface::class => AnnouncementRepository::class,
        QuranReviewSessionRepositoryInterface::class => QuranReviewSessionRepository::class,
        RewardPointRepositoryInterface::class => RewardPointRepository::class,
        HomeworkRepositoryInterface::class => HomeworkRepository::class,
        LessonRepositoryInterface::class => LessonRepository::class,
        MessageRepositoryInterface::class => MessageRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    public function boot(): void
    {
        //
    }
}
