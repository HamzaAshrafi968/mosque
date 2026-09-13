<?php

namespace App\Console\Commands;

use App\Models\QuranAyah;
use Database\Seeders\QuranPageSeeder;
use Illuminate\Console\Command;

class FillQuranPages extends Command
{
    protected $signature = 'quran:pages';

    protected $description = 'تعبئة أرقام الصفحات والأجزاء لآيات القرآن من ملف التخطيط المدني';

    public function handle(): int
    {
        if (! file_exists(database_path('data/quran_pages.json'))) {
            $this->error('ملف database/data/quran_pages.json غير موجود — شغّل: php database/fetch-quran-pages.php');

            return self::FAILURE;
        }

        if (! QuranAyah::query()->exists()) {
            $this->error('لا توجد آيات في قاعدة البيانات — شغّل أولاً: php artisan db:seed --class=QuranDataSeeder');

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => QuranPageSeeder::class,
            '--force' => true,
        ]);

        return self::SUCCESS;
    }
}
