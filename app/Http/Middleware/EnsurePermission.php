<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Models\User;
use App\Services\AuthorizationService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Subjects whose `user_id` links them to their own portal account rather
     * than pointing at an owning user. Their domain scoping is enforced by the
     * controllers (e.g. QuranScopeService::assertCanManageStudent).
     */
    private const PORTAL_LINKED_SUBJECTS = [
        Student::class,
    ];

    public function __construct(private readonly AuthorizationService $authorization) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $subject = $this->boundSubject($request);

        if (! $this->authorization->canAny($user, $permissions, $subject, $this->ownershipPredicate())) {
            abort(403, 'ليس لديك صلاحية لتنفيذ هذا الإجراء');
        }

        return $next($request);
    }

    /** The first route-model-bound Eloquent model, when present. */
    private function boundSubject(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * "own" scope predicate: the subject must reference the acting user
     * (user_id / awarded_by / created_by / recorded_by) or their teacher
     * profile (teacher_id / supervisor_id). A subject without any ownership
     * column falls back to the tenant-isolation check already applied by
     * can(); when ownership columns exist but none match, access is denied.
     */
    private function ownershipPredicate(): Closure
    {
        return function (User $user, ?Model $subject): bool {
            if (! $subject instanceof Model) {
                return true;
            }

            $references = [
                'user_id' => $user->id,
                'awarded_by' => $user->id,
                'created_by' => $user->id,
                'recorded_by' => $user->id,
                'teacher_id' => $user->teacher?->id,
                'supervisor_id' => $user->teacher?->id,
            ];

            if (in_array($subject::class, self::PORTAL_LINKED_SUBJECTS, true)) {
                unset($references['user_id']);
            }

            $hasOwnershipColumn = false;

            foreach ($references as $attribute => $expected) {
                $value = $subject->getAttribute($attribute);

                if ($value === null) {
                    continue;
                }

                $hasOwnershipColumn = true;

                if ($expected !== null && (string) $value === (string) $expected) {
                    return true;
                }
            }

            return ! $hasOwnershipColumn;
        };
    }
}
