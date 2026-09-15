<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;

class PurgeExpiredAnnouncements extends Command
{
    protected $signature = 'announcements:purge-expired
                            {--dry-run : عرض عدد الإعلانات المنتهية دون حذفها}';

    protected $description = 'حذف الإعلانات الصوتية المنتهية (بعد أسبوع من النشر) مع ملفاتها الصوتية';

    public function handle(): int
    {
        // Console runs outside a tenant context, so the tenant scope must be
        // bypassed to purge expired announcements across every mosque.
        $query = Announcement::query()
            ->withoutGlobalScope('tenant')
            ->expired();

        if ($this->option('dry-run')) {
            $this->info('إعلانات منتهية الصلاحية: '.$query->count());

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->chunkById(100, function ($announcements) use (&$deleted) {
            foreach ($announcements as $announcement) {
                $announcement->delete();
                $deleted++;
            }
        });

        $this->info("تم حذف {$deleted} إعلان منتهي الصلاحية.");

        return self::SUCCESS;
    }
}
