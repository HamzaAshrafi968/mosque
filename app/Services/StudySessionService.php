<?php

namespace App\Services;

use App\Models\StudySession;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Study sessions (الدوامين) provisioning & current-selection helpers.
 *
 * Every mosque gets two default sessions (الأول والثاني); mosque managers
 * can add / rename / archive more from the admin panel.
 */
class StudySessionService
{
    public const DEFAULT_SESSIONS = ['الدوام الأول', 'الدوام الثاني'];

    /**
     * Create the two default sessions for a mosque (idempotent).
     */
    public function provisionTenantSessions(Tenant $tenant): void
    {
        if (DB::table('study_sessions')->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (self::DEFAULT_SESSIONS as $name) {
            StudySession::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    /**
     * الدوام المحدد حالياً من مدير الجامع (null = كل الدوامات).
     */
    public function currentSession(?string $tenantId): ?StudySession
    {
        $sessionId = session('study_session_id');

        if ($sessionId === null || $tenantId === null) {
            return null;
        }

        return StudySession::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($sessionId)
            ->first();
    }

    /**
     * The session currently selected by the logged-in manager (null = all).
     */
    public function currentSessionId(?string $tenantId): ?string
    {
        return $this->currentSession($tenantId)?->id;
    }
}
