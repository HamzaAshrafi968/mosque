<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_certificates', function (Blueprint $table) {
            $table->date('granted_at')->nullable()->after('issuer');
        });

        DB::table('teacher_certificates')->whereNotNull('year')->get(['id', 'year'])->each(function ($certificate) {
            $year = trim((string) $certificate->year);

            if ($year === '') {
                return;
            }

            $date = preg_match('/^\d{4}$/', $year) ? $year.'-01-01' : null;

            if ($date === null) {
                try {
                    $date = Carbon::parse($year)->toDateString();
                } catch (Throwable) {
                    $date = null;
                }
            }

            if ($date !== null) {
                DB::table('teacher_certificates')->where('id', $certificate->id)->update(['granted_at' => $date]);
            }
        });

        Schema::table('teacher_certificates', function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_certificates', function (Blueprint $table) {
            $table->string('year')->nullable()->after('issuer');
        });

        DB::table('teacher_certificates')->whereNotNull('granted_at')->get(['id', 'granted_at'])->each(function ($certificate) {
            DB::table('teacher_certificates')->where('id', $certificate->id)->update([
                'year' => Carbon::parse($certificate->granted_at)->year,
            ]);
        });

        Schema::table('teacher_certificates', function (Blueprint $table) {
            $table->dropColumn('granted_at');
        });
    }
};
