<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use App\Support\AuditActionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * سجل العمليات معرّب بالكامل: أسماء الكيانات والإجراءات والحقول والقيم
 * لا تخلط العربية بالإنجليزية.
 */
class AuditLogArabicTest extends TestCase
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

    private function assertArabicOnly(string $value, string $context): void
    {
        $this->assertMatchesRegularExpression(
            '/^[\p{Arabic}\s()]+$/u',
            $value,
            "التسمية غير معرّبة ({$context}): {$value}"
        );
    }

    public function test_entity_labels_are_fully_arabic(): void
    {
        $types = [
            'hafiz_monthly_exam', 'section_student', 'section_teacher',
            'homework_submission', 'qualifying_weekly_evaluation', 'unknown_entity',
        ];

        foreach ($types as $type) {
            $this->assertArabicOnly(AuditActionCatalog::entityLabel($type), 'entity:'.$type);
        }
    }

    public function test_action_labels_are_fully_arabic(): void
    {
        $actions = [
            'attendance.session_updated', 'qualifying.completed', 'ijazah.completed',
            'student.enrolled', 'student.enrollment.reactivated', 'unknown.action',
        ];

        foreach ($actions as $action) {
            $this->assertArabicOnly(AuditActionCatalog::actionLabel($action), 'action:'.$action);
        }
    }

    public function test_field_labels_and_values_are_fully_arabic(): void
    {
        foreach (['from_juz', 'to_juz', 'confirmed_at', 'exam_status', 'unknown_field'] as $field) {
            $this->assertArabicOnly(AuditActionCatalog::fieldLabel($field), 'field:'.$field);
        }

        $this->assertSame('من الجزء', AuditActionCatalog::fieldLabel('from_juz'));
        $this->assertSame('تاريخ التأكيد', AuditActionCatalog::fieldLabel('confirmed_at'));
        $this->assertSame('نشط', AuditActionCatalog::formatValue('active', 0, 'status'));
        $this->assertSame('مكتمل', AuditActionCatalog::formatValue('completed', 0, 'status'));
        $this->assertSame('ذكر', AuditActionCatalog::formatValue('male', 0, 'gender'));
        $this->assertSame('تأهيلي', AuditActionCatalog::formatValue('qualifying', 0, 'program_type'));
        $this->assertSame('وارد', AuditActionCatalog::formatValue('money_in', 0, 'direction'));
        $this->assertSame('حاضر', AuditActionCatalog::formatValue('present', 0, 'statuses'));
    }

    public function test_audit_page_renders_arabic_only(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $mosque->id,
            'user_id' => $manager->id,
            'action' => 'qualifying.completed',
            'entity_type' => 'hafiz_monthly_exam',
            'entity_id' => (string) Str::uuid(),
            'before' => ['status' => 'active', 'from_juz' => 1],
            'after' => ['status' => 'completed', 'to_juz' => 2],
        ]);

        $this->actingAs($manager)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('إتمام البرنامج التأهيلي')
            ->assertSee('امتحان شهري للحافظ')
            ->assertSee('نشط')
            ->assertSee('مكتمل')
            ->assertSee('من الجزء')
            ->assertSee('إلى الجزء')
            ->assertDontSee('qualifying.completed')
            ->assertDontSee('hafiz monthly exam')
            ->assertDontSee('>active<', false)
            ->assertDontSee('>completed<', false);
    }
}
