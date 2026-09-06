<?php

namespace App\Traits;

use App\Models\StudySession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filters a model by the currently selected دوام (study session / shift).
 *
 * The active session comes from `config('app.current_study_session_id')`,
 * set by InitializeTenant from the session the mosque manager chose.
 * When null (or when the selection does not exist) every record is visible
 * ("كل الدوامات").
 */
trait StudySessionScopedTrait
{
    protected static function bootStudySessionScopedTrait(): void
    {
        static::addGlobalScope('study_session', function (Builder $builder) {
            $sessionId = config('app.current_study_session_id');

            if ($sessionId !== null) {
                $builder->where($builder->getModel()->getTable().'.study_session_id', $sessionId);
            }
        });
    }

    public function studySession(): BelongsTo
    {
        return $this->belongsTo(StudySession::class);
    }
}
