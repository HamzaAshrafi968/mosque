<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Student attendance moved from the legacy date-based `attendances` rows
 * into session-based `attendance_sessions` + `attendance_records`.
 *
 * Existing student rows are re-created as (section, date) sessions and the
 * legacy student rows are removed. Teacher attendance rows stay in
 * `attendances` (teacher attendance is out of scope of the sessions model).
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasStudentRows = DB::table('attendances')->whereNotNull('student_id')->exists();

        if (! $hasStudentRows) {
            return;
        }

        // Legacy rows carry no section; a session needs one. Best effort:
        // resolve each student's current section snapshot at migration time.
        $studentSections = DB::table('students')
            ->whereNotNull('section_id')
            ->pluck('section_id', 'id');

        $legacy = DB::table('attendances')
            ->whereNotNull('student_id')
            ->orderBy('date')
            ->get();

        foreach ($legacy as $row) {
            $sectionId = $studentSections[$row->student_id] ?? null;

            if ($sectionId === null) {
                continue;
            }

            $sessionId = DB::table('attendance_sessions')
                ->where('tenant_id', $row->tenant_id)
                ->where('section_id', $sectionId)
                ->where('date', $row->date)
                ->value('id');

            if (! $sessionId) {
                $sessionId = (string) Str::uuid();
                DB::table('attendance_sessions')->insert([
                    'id' => $sessionId,
                    'tenant_id' => $row->tenant_id,
                    'section_id' => $sectionId,
                    'date' => $row->date,
                    'status' => 'completed',
                    'created_by' => $row->recorded_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            DB::table('attendance_records')->updateOrInsert(
                [
                    'tenant_id' => $row->tenant_id,
                    'attendance_session_id' => $sessionId,
                    'student_id' => $row->student_id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'status' => $row->status,
                    'note' => $row->notes,
                    'created_at' => $row->created_at,
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('attendances')->whereNotNull('student_id')->delete();
    }

    public function down(): void
    {
        // Legacy student rows cannot be reconstructed reliably; nothing to restore.
    }
};
