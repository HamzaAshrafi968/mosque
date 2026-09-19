<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSlot;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * تصدير كشوف الشهر: CSV + Excel (.xlsx حقيقي) + نسخ الطباعة/PDF.
 */
class PayrollExportTest extends TestCase
{
    use RefreshDatabase;

    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(Tenant $mosque, array $attributes = []): array
    {
        $user = User::factory()->for($mosque)->create();
        $teacher = Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $user->id, ...$attributes]);

        return [$user, $teacher];
    }

    private function slot(Tenant $mosque, Teacher $teacher): void
    {
        WorkSlot::create([
            'tenant_id' => $mosque->id,
            'teacher_id' => $teacher->id,
            'date' => '2026-09-20',
            'start_time' => '09:00',
            'end_time' => '15:00',
            'duration_minutes' => 360,
        ]);
    }

    public function test_csv_export_contains_the_month_rows(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 1500]);
        $this->slot($mosque, $teacher);

        $response = $this->actingAs($manager)
            ->get(route('admin.payroll.export', ['month' => '2026-09']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();

        $this->assertStringContainsString($teacher->name, $content);
        $this->assertStringContainsString('1500.00', $content);
        $this->assertStringContainsString('المعلم', $content);
    }

    public function test_excel_export_is_a_valid_xlsx_with_the_teacher_row(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 1500]);
        $this->slot($mosque, $teacher);

        $response = $this->actingAs($manager)
            ->get(route('admin.payroll.export-excel', ['month' => '2026-09']))
            ->assertOk();

        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );

        $content = $response->streamedContent();
        $this->assertSame('PK', substr($content, 0, 2), 'xlsx must be a zip package');

        $temp = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($temp, $content);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp) === true);

        libxml_use_internal_errors(true);

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml'] as $part) {
            $xml = $zip->getFromName($part);

            $this->assertNotFalse($xml, "missing xlsx part: {$part}");
            $this->assertNotFalse(@simplexml_load_string((string) $xml), "invalid XML in {$part}");
        }

        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString($teacher->name, $sheet);
        $this->assertStringContainsString('inlineStr', $sheet);
        $this->assertStringContainsString('rightToLeft="1"', $sheet);

        $zip->close();
        @unlink($temp);
    }

    /** @return array<int, string> */
    private function zipNames(ZipArchive $zip): array
    {
        $names = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        return $names;
    }

    public function test_print_pages_render_for_all_teachers_and_for_one_teacher(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        [, $teacher] = $this->teacher($mosque, ['pay_type' => 'monthly', 'monthly_salary' => 1500]);
        $this->slot($mosque, $teacher);

        $this->actingAs($manager)
            ->get(route('admin.payroll.print', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('كشوف رواتب المعلمين')
            ->assertSee($teacher->name)
            ->assertSee('1,500.00');

        $this->actingAs($manager)
            ->get(route('admin.payroll.sheet-print', ['teacher' => $teacher, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('كشف راتب')
            ->assertSee($teacher->name)
            ->assertSee('09:00');
    }

    public function test_exports_follow_the_payroll_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $permissions = Permission::query()->whereIn('code', ['payroll.view', 'finance.view'])->pluck('id');
        Role::query()
            ->where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permissions->all());

        $this->actingAs($manager)
            ->get(route('admin.payroll.export-excel', ['month' => '2026-09']))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('admin.payroll.print', ['month' => '2026-09']))
            ->assertForbidden();
    }
}
