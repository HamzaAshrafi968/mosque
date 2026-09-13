<?php

namespace Tests\Feature;

use App\Models\QuranAyah;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentQuranRecordTest extends TestCase
{
    private function adminWithMosque(): User
    {
        $tenant = Tenant::factory()->create();
        config(['app.current_tenant_id' => $tenant->id]);

        return User::factory()->admin()->for($tenant)->create();
    }

    private function surah(string $name, int $numAyahs, int $sortOrder): QuranSurah
    {
        return QuranSurah::create([
            'name_arabic' => $name,
            'revelation_type' => 'makkiah',
            'num_ayahs' => $numAyahs,
            'sort_order' => $sortOrder,
        ]);
    }

    private function ayah(QuranSurah $surah, int $number): QuranAyah
    {
        return QuranAyah::create([
            'surah_id' => $surah->id,
            'ayah_number' => $number,
            'text' => 'نص الآية',
            'text_simple' => 'نص الآية',
        ]);
    }

    public function test_admin_can_create_student_with_quran_record_from_web_form(): void
    {
        $admin = $this->adminWithMosque();

        $from = $this->surah('البقرة', 286, 2);
        $to = $this->surah('الكهف', 110, 18);
        $this->ayah($from, 1);
        $this->ayah($to, 20);

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'name' => 'طالب حفظ',
            'gender' => 'male',
            'memorized_juz' => 5.5,
            'memorized_from_surah_id' => $from->id,
            'memorized_from_ayah' => 1,
            'memorized_to_surah_id' => $to->id,
            'memorized_to_ayah' => 20,
        ])->assertRedirect(route('admin.students.index'));

        $student = Student::where('name', 'طالب حفظ')->firstOrFail();

        $this->assertSame('5.5', $student->memorized_juz);
        $this->assertSame($from->id, $student->memorized_from_surah_id);
        $this->assertSame(1, $student->memorized_from_ayah);
        $this->assertSame(20, $student->memorized_to_ayah);

        $this->actingAs($admin)
            ->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('سجل الحفظ القرآني')
            ->assertSee('من سورة البقرة (آية 1) إلى سورة الكهف (آية 20)');
    }

    public function test_admin_can_create_student_with_quran_record_via_api(): void
    {
        $admin = $this->adminWithMosque();
        Sanctum::actingAs($admin);

        $surah = $this->surah('الناس', 6, 114);
        $this->ayah($surah, 1);

        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب حفظ',
            'gender' => 'male',
            'memorized_juz' => 2.5,
            'memorized_from_surah_id' => $surah->id,
            'memorized_from_ayah' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.memorized_juz', 2.5)
            ->assertJsonPath('data.memorized_from_surah_id', $surah->id)
            ->assertJsonPath('data.memorized_from_ayah', 1)
            ->assertJsonPath('data.memorized_range', 'من سورة الناس (آية 1)');
    }

    public function test_quran_record_ayah_must_belong_to_selected_surah(): void
    {
        $admin = $this->adminWithMosque();
        Sanctum::actingAs($admin);

        $surah = $this->surah('الفلق', 5, 113);
        $this->ayah($surah, 1);

        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب',
            'gender' => 'male',
            'memorized_from_surah_id' => $surah->id,
            'memorized_from_ayah' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('memorized_from_ayah');

        // Selecting a surah without an ayah is rejected too (range needs both).
        $this->postJson('/api/v1/admin/students', [
            'name' => 'طالب',
            'gender' => 'male',
            'memorized_from_surah_id' => $surah->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('memorized_from_ayah');
    }

    public function test_api_update_can_clear_quran_record(): void
    {
        $admin = $this->adminWithMosque();
        Sanctum::actingAs($admin);

        $surah = $this->surah('الإخلاص', 4, 112);
        $this->ayah($surah, 1);

        $student = Student::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'طالب',
            'gender' => 'male',
            'memorized_juz' => 3,
            'memorized_from_surah_id' => $surah->id,
            'memorized_from_ayah' => 1,
        ]);

        $this->putJson("/api/v1/admin/students/{$student->id}", [
            'name' => 'طالب',
            'gender' => 'male',
            'memorized_juz' => null,
            'memorized_from_surah_id' => null,
            'memorized_from_ayah' => null,
        ])->assertOk();

        $student->refresh();

        $this->assertNull($student->memorized_juz);
        $this->assertNull($student->memorized_from_surah_id);
        $this->assertNull($student->memorized_from_ayah);
    }
}
