<?php

namespace App\Services;

use App\Jobs\SendPortalNotification;
use App\Models\ParentStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * In-app (database) notifications for the portals (spec §39).
 *
 * Notification visibility is per user; events targeting a student fan out to
 * the student's own portal account (when it exists) and to every guardian
 * linked through parent_students.
 *
 * Delivery is asynchronous: send() dispatches SendPortalNotification jobs on
 * the `notifications` queue (one job per 500 recipients), so large fan-outs
 * never block the HTTP request. In tests (QUEUE_CONNECTION=sync) delivery
 * stays synchronous.
 */
class NotificationService
{
    /** Send to an explicit list of users (deduplicated). */
    public function send(iterable $users, string $title, string $body, ?string $url = null): void
    {
        $ids = collect($users)
            ->map(fn ($user) => $user instanceof User ? $user : User::find($user))
            ->filter()
            ->map(fn (User $user) => $user->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        foreach ($ids->chunk(500) as $chunk) {
            SendPortalNotification::dispatch($chunk->all(), $title, $body, $url);
        }
    }

    /**
     * Notify the student portal account (when linked) and all guardians of the
     * student (spec §39 fan-out). Optionally notify staff (teachers/admins).
     */
    public function notifyStudentCircle(Student $student, string $title, string $body, ?string $url = null, bool $staffToo = false): void
    {
        $recipients = collect();

        if ($student->user_id) {
            $recipients->push($student->user_id);
        }

        $recipients = $recipients->merge(
            $student->guardians()
                ->whereNotNull('user_id')
                ->pluck('parents.user_id')
        );

        if ($staffToo) {
            $recipients = $recipients->merge(
                User::query()
                    ->where('tenant_id', $student->tenant_id)
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEACHER])
                    ->pluck('id')
            );
        }

        $this->send($recipients, $title, $body, $url);
    }

    /** Recipient ids for a whole student collection (avoid N+1 notifications). */
    public function recipientsForStudents(Collection $students, bool $staffToo = false): Collection
    {
        $studentUserIds = $students->pluck('user_id')->filter();

        $guardianUserIds = ParentStudent::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereNotNull('parent_id')
            ->whereHas('guardian', fn ($q) => $q->whereNotNull('user_id'))
            ->with('guardian.user:id')
            ->get()
            ->pluck('guardian.user.id')
            ->filter();

        $ids = $studentUserIds->merge($guardianUserIds);

        if ($staffToo) {
            $ids = $ids->merge(
                User::query()
                    ->where('tenant_id', $students->first()?->tenant_id)
                    ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEACHER])
                    ->pluck('id')
            );
        }

        return $ids->unique()->values();
    }

    /** Fan out to the portal accounts + guardians of a roster of students. */
    public function notifyRoster(Collection $students, string $title, string $body, ?string $url = null): void
    {
        $this->send($this->recipientsForStudents($students), $title, $body, $url);
    }
}
