<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class PurgeExpiredSchedules extends Command
{
    protected $signature = 'schedules:purge-expired
                            {--dry-run : عرض عدد الحصص المنتهية دون حذفها}';

    protected $description = 'حذف الحصص المنتهية الصلاحية (يوم/أسبوع/شهر/حتى انتهاء الدورة) مع استثناءاتها';

    public function handle(): int
    {
        // Console runs outside a tenant/shift context, so both global scopes
        // must be bypassed to purge expired schedules across every mosque.
        $query = Schedule::query()
            ->withoutGlobalScope('tenant')
            ->withoutGlobalScope('study_session')
            ->expired();

        if ($this->option('dry-run')) {
            $this->info('حصص منتهية الصلاحية: '.$query->count());

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->chunkById(100, function ($schedules) use (&$deleted) {
            foreach ($schedules as $schedule) {
                $schedule->delete();
                $deleted++;
            }
        });

        $this->info("تم حذف {$deleted} حصة منتهية الصلاحية.");

        return self::SUCCESS;
    }
}
