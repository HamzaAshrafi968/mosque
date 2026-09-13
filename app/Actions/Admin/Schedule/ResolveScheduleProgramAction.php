<?php

namespace App\Actions\Admin\Schedule;

use App\Models\ProgramPeriod;
use App\Services\ProgramService;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the program/period relation of a schedule payload and inherits the
 * period times when they are left empty. A period must belong to the chosen
 * program, and the program must be enabled for the chosen دوام (shift).
 * Shared by the admin Blade and API schedule controllers.
 */
class ResolveScheduleProgramAction
{
    public function __construct(private readonly ProgramService $programs) {}

    /**
     * @throws ValidationException
     */
    public function execute(array $data): array
    {
        if (empty($data['program_period_id'])) {
            $this->assertValidRange($data);
            $this->assertProgramAllowed($data);

            return $data;
        }

        $period = ProgramPeriod::findOrFail($data['program_period_id']);

        if (! empty($data['program_id']) && $period->program_id !== $data['program_id']) {
            throw ValidationException::withMessages([
                'program_period_id' => 'الفترة المختارة لا تنتمي للبرنامج المحدد',
            ]);
        }

        $data['program_id'] = $period->program_id;
        $data['starts_at'] = $data['starts_at'] ?? $period->starts_at;
        $data['ends_at'] = $data['ends_at'] ?? $period->ends_at;

        if (empty($data['starts_at']) || empty($data['ends_at'])) {
            throw ValidationException::withMessages([
                'starts_at' => 'الفترة المختارة بلا أوقات محددة — أدخل الأوقات يدوياً',
            ]);
        }

        $this->assertValidRange($data);
        $this->assertProgramAllowed($data);

        return $data;
    }

    /** @throws ValidationException */
    private function assertValidRange(array $data): void
    {
        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && $data['ends_at'] <= $data['starts_at']) {
            throw ValidationException::withMessages([
                'ends_at' => 'يجب أن يكون وقت النهاية بعد وقت البداية',
            ]);
        }
    }

    /** البرنامج يجب أن يكون متاحاً في الدوام المختار (إن كان للدوام قيود). */
    private function assertProgramAllowed(array $data): void
    {
        if (! $this->programs->programAllowedInSession($data['program_id'] ?? null, $data['study_session_id'] ?? null)) {
            throw ValidationException::withMessages([
                'program_id' => 'البرنامج المختار غير متاح في هذا الدوام — فعّله للدوام من صفحة الدوامات',
            ]);
        }
    }
}
