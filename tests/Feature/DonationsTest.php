<?php

namespace Tests\Feature;

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PortalNotification;
use App\Services\RoleService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * التبرعات والمساهمات: تقديم زوار الموقع العام (بانتظار المراجعة)،
 * قبول/رفض المدير، الظهور العلني للمقبول فقط، والعزل بالصلاحيات.
 */
class DonationsTest extends TestCase
{
    private function mosque(array $attributes = []): Tenant
    {
        $mosque = Tenant::factory()->create($attributes);
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function publicMosque(): Tenant
    {
        return Tenant::factory()->create(['code' => 'noor', 'status' => Tenant::STATUS_ACTIVE]);
    }

    private function manager(Tenant $mosque): User
    {
        return User::factory()->admin()->for($mosque)->create();
    }

    private function publicPayload(Tenant $mosque, array $overrides = []): array
    {
        return array_merge([
            'mosque_id' => $mosque->id,
            'type' => 'in_kind',
            'donor_name' => 'أبو خالد',
            'donor_phone' => '0999999999',
            'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'description' => 'عندي مسبح وأريد أن يسبح الطلاب مجانًا',
        ], $overrides);
    }

    public function test_public_visitor_submission_is_stored_as_pending_for_the_chosen_mosque(): void
    {
        $mosque = $this->publicMosque();

        $this->post(route('site.donations.store'), $this->publicPayload($mosque))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('donations', [
            'tenant_id' => $mosque->id,
            'status' => 'pending',
            'source' => 'public',
            'type' => 'in_kind',
            'title' => null,
            'amount' => null,
            'currency' => null,
        ]);
    }

    public function test_submission_can_include_an_optional_title_and_description(): void
    {
        $mosque = $this->publicMosque();

        $this->post(route('site.donations.store'), $this->publicPayload($mosque, [
            'title' => 'مسبح مجاني لطلاب الجامع',
            'description' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('donations', [
            'title' => 'مسبح مجاني لطلاب الجامع',
            'description' => null,
        ]);
    }

    public function test_financial_donation_requires_amount_and_currency(): void
    {
        $mosque = $this->publicMosque();

        $this->from(route('site.mosques.show', $mosque))
            ->post(route('site.donations.store'), $this->publicPayload($mosque, ['type' => 'financial']))
            ->assertSessionHasErrors(['amount', 'currency']);

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_submission_requires_phone_and_delivery_date(): void
    {
        $mosque = $this->publicMosque();

        $this->from(route('site.mosques.show', $mosque))
            ->post(route('site.donations.store'), $this->publicPayload($mosque, [
                'donor_phone' => '',
                'delivery_date' => '',
            ]))
            ->assertSessionHasErrors(['donor_phone', 'delivery_date']);

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_financial_donation_stores_amount_and_currency(): void
    {
        $mosque = $this->publicMosque();

        $this->post(route('site.donations.store'), $this->publicPayload($mosque, [
            'type' => 'financial',
            'amount' => 50,
            'currency' => 'USD',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('donations', [
            'type' => 'financial',
            'amount' => 50,
            'currency' => 'USD',
        ]);
    }

    public function test_submission_rejects_unknown_or_non_public_mosque(): void
    {
        $hidden = Tenant::factory()->create(['status' => Tenant::STATUS_ARCHIVED]);

        $this->post(route('site.donations.store'), $this->publicPayload($hidden))
            ->assertSessionHasErrors('mosque_id');

        $this->post(route('site.donations.store'), $this->publicPayload($hidden, ['mosque_id' => 'no-such-id']))
            ->assertSessionHasErrors('mosque_id');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_only_accepted_donations_appear_on_the_public_mosque_page(): void
    {
        $mosque = $this->publicMosque();

        Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'in_kind',
            'status' => 'accepted',
            'source' => 'public',
            'donor_name' => 'متبرع مقبول',
            'title' => 'مسبح للطلاب',
            'description' => 'سباحة مجانية',
        ]);
        Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'moral',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متبرع منتظر',
            'title' => 'ورشة بانتظار المراجعة',
        ]);
        Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'financial',
            'status' => 'rejected',
            'source' => 'public',
            'donor_name' => 'متبرع مرفوض',
            'title' => 'تبرع مرفوض',
            'amount' => 25,
            'currency' => 'USD',
        ]);

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('مسبح للطلاب')
            ->assertSee('متبرع مقبول')
            ->assertDontSee('ورشة بانتظار المراجعة')
            ->assertDontSee('تبرع مرفوض');
    }

    public function test_anonymous_donation_hides_the_donor_name_publicly(): void
    {
        $mosque = $this->publicMosque();

        Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'in_kind',
            'status' => 'accepted',
            'source' => 'public',
            'donor_name' => 'الاسم الحقيقي',
            'is_anonymous' => true,
            'title' => 'مكتبة كاملة للطلاب',
        ]);

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('متبرع كريم')
            ->assertDontSee('الاسم الحقيقي');
    }

    public function test_public_submission_notifies_the_mosque_managers(): void
    {
        Notification::fake();

        $mosque = Tenant::factory()->create(['code' => 'noor']);
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);
        $manager = $this->manager($mosque);

        $this->post(route('site.donations.store'), $this->publicPayload($mosque))->assertSessionHasNoErrors();

        Notification::assertSentTo($manager, PortalNotification::class);
    }

    public function test_accepting_a_donation_notifies_all_portal_roles(): void
    {
        Notification::fake();

        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_STUDENT]);
        $guardianUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_GUARDIAN]);
        $teacherUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_TEACHER]);

        $donation = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'in_kind',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'أبو خالد',
            'title' => 'مسبح مجاني لطلاب الجامع',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.donations.accept', $donation))
            ->assertRedirect();

        foreach ([$studentUser, $guardianUser, $teacherUser, $manager] as $user) {
            Notification::assertSentTo($user, PortalNotification::class, function (PortalNotification $notification) {
                return $notification->title === 'تبرع جديد من أهل الخير'
                    && str_contains($notification->body, 'مسبح مجاني لطلاب الجامع');
            });
        }
    }

    public function test_manually_recorded_accepted_donation_notifies_the_mosque(): void
    {
        Notification::fake();

        $mosque = $this->mosque();
        $manager = $this->manager($mosque);
        $studentUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_STUDENT]);

        $this->actingAs($manager)
            ->post(route('admin.donations.store'), [
                'type' => 'financial',
                'status' => 'accepted',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'تبرع مادي للمسجد',
                'amount' => 100,
                'currency' => 'USD',
            ])
            ->assertRedirect(route('admin.donations.index'));

        Notification::assertSentTo($studentUser, PortalNotification::class, function (PortalNotification $notification) {
            return str_contains($notification->body, 'تبرع مادي للمسجد')
                && str_contains($notification->body, '100');
        });
    }

    public function test_manager_reviews_accepts_and_rejects_donations(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $pending = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'moral',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متطوع',
            'title' => 'تدريس تطوعي',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.donations.index'))
            ->assertOk()
            ->assertSee('تدريس تطوعي')
            ->assertSee('قبول');

        $this->actingAs($manager)
            ->post(route('admin.donations.accept', $pending))
            ->assertRedirect();

        $this->assertDatabaseHas('donations', ['id' => $pending->id, 'status' => 'accepted', 'reviewed_by' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.accepted']);

        $rejected = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'in_kind',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متبرع',
            'title' => 'عرض غير مناسب',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.donations.reject', $rejected), ['reject_reason' => 'خارج نطاق الحاجة'])
            ->assertRedirect();

        $this->assertDatabaseHas('donations', ['id' => $rejected->id, 'status' => 'rejected', 'reject_reason' => 'خارج نطاق الحاجة']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.rejected']);
    }

    public function test_manager_can_record_a_donation_manually_and_it_shows_publicly(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->post(route('admin.donations.store'), [
                'type' => 'financial',
                'status' => 'accepted',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'تبرع مادي للمسجد',
                'amount' => 100,
                'currency' => 'USD',
            ])
            ->assertRedirect(route('admin.donations.index'));

        $this->assertDatabaseHas('donations', [
            'source' => 'admin',
            'status' => 'accepted',
            'title' => 'تبرع مادي للمسجد',
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // صفحة الجامع العامة تعرضه فورًا لأنه مقبول.
        $mosque->update(['code' => 'noor', 'status' => Tenant::STATUS_ACTIVE]);

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('تبرع مادي للمسجد')
            ->assertSee('100');
    }

    public function test_admin_can_edit_and_delete_donations(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $donation = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'in_kind',
            'status' => 'accepted',
            'source' => 'admin',
            'donor_name' => 'فاعل خير',
            'title' => 'قبل التعديل',
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.donations.update', $donation), [
                'type' => 'in_kind',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'بعد التعديل',
            ])
            ->assertRedirect(route('admin.donations.index'));

        $this->assertDatabaseHas('donations', ['id' => $donation->id, 'title' => 'بعد التعديل']);

        $this->actingAs($manager)
            ->delete(route('admin.donations.destroy', $donation))
            ->assertRedirect();

        $this->assertDatabaseMissing('donations', ['id' => $donation->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.deleted']);
    }

    public function test_donations_are_isolated_per_mosque_in_admin_index(): void
    {
        $mosqueA = $this->mosque();
        $managerA = $this->manager($mosqueA);

        Donation::create([
            'tenant_id' => $mosqueA->id,
            'type' => 'moral',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متطوع أول',
            'title' => 'ورشة الجامع الأول',
        ]);

        $mosqueB = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosqueB->id]);
        app(RoleService::class)->provisionTenantRoles($mosqueB);
        $managerB = $this->manager($mosqueB);

        Donation::create([
            'tenant_id' => $mosqueB->id,
            'type' => 'moral',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متطوع ثان',
            'title' => 'ورشة الجامع الثاني',
        ]);

        $this->actingAs($managerB)
            ->get(route('admin.donations.index'))
            ->assertOk()
            ->assertSee('ورشة الجامع الثاني')
            ->assertDontSee('ورشة الجامع الأول');
    }

    public function test_teacher_cannot_manage_donations(): void
    {
        $mosque = $this->mosque();
        $teacher = User::factory()->for($mosque)->create();

        $this->actingAs($teacher)
            ->get(route('admin.donations.index'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->post(route('admin.donations.store'), [
                'type' => 'moral',
                'donor_name' => 'متبرع',
                'title' => 'محاولة معلم',
            ])
            ->assertForbidden();
    }

    public function test_review_buttons_and_routes_follow_approve_permission(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $donation = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'moral',
            'status' => 'pending',
            'source' => 'public',
            'donor_name' => 'متطوع',
            'title' => 'مساعدة تطوعية',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.donations.index'))
            ->assertOk()
            ->assertSee(route('admin.donations.accept', $donation), false);

        $permission = Permission::where('code', 'donations.approve')->firstOrFail();

        Role::where('tenant_id', $mosque->id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->firstOrFail()
            ->permissions()
            ->detach($permission->id);

        $this->actingAs($manager)
            ->get(route('admin.donations.index'))
            ->assertOk()
            ->assertDontSee(route('admin.donations.accept', $donation), false);

        $this->actingAs($manager)
            ->post(route('admin.donations.accept', $donation))
            ->assertForbidden();

        $this->assertDatabaseHas('donations', ['id' => $donation->id, 'status' => DonationStatus::Pending->value]);
    }

    public function test_mosque_public_page_shows_the_donation_bar_and_form(): void
    {
        $mosque = $this->publicMosque();

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('شاركنا الخير')
            ->assertSee('تبرع الآن')
            ->assertSee(route('site.donations.store'), false);
    }

    public function test_donation_bar_appears_inside_the_admin_and_teacher_portals(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('شاركنا الخير')
            ->assertSee(route('site.donations.store'), false);

        $teacherUser = User::factory()->create(['tenant_id' => $mosque->id, 'role' => User::ROLE_TEACHER]);
        Teacher::factory()->create(['tenant_id' => $mosque->id, 'user_id' => $teacherUser->id]);

        $this->actingAs($teacherUser)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('شاركنا الخير')
            ->assertSee(route('site.donations.store'), false);
    }

    public function test_other_type_requires_a_custom_type_text(): void
    {
        $mosque = $this->publicMosque();

        $this->from(route('site.mosques.show', $mosque))
            ->post(route('site.donations.store'), $this->publicPayload($mosque, ['type' => 'other']))
            ->assertSessionHasErrors('custom_type');

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_other_type_stores_the_custom_type_text_and_no_amount(): void
    {
        $mosque = $this->publicMosque();

        $this->post(route('site.donations.store'), $this->publicPayload($mosque, [
            'type' => 'other',
            'custom_type' => 'توفير مواصلات للطلاب',
            'amount' => 999,
            'currency' => 'USD',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('donations', [
            'type' => 'other',
            'custom_type' => 'توفير مواصلات للطلاب',
            'amount' => null,
            'currency' => null,
        ]);
    }

    public function test_custom_type_text_is_cleared_for_known_types(): void
    {
        $mosque = $this->publicMosque();

        $this->post(route('site.donations.store'), $this->publicPayload($mosque, [
            'type' => 'moral',
            'custom_type' => 'يجب تجاهله',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('donations', [
            'type' => 'moral',
            'custom_type' => null,
        ]);
    }

    public function test_other_type_shows_custom_label_publicly_and_in_admin_index(): void
    {
        $mosque = $this->publicMosque();
        $manager = $this->manager($mosque);

        $donation = Donation::create([
            'tenant_id' => $mosque->id,
            'type' => 'other',
            'custom_type' => 'تجهيز مكتبة مدرسية',
            'status' => 'accepted',
            'source' => 'public',
            'donor_name' => 'متبرع',
            'title' => 'مكتبة جديدة',
            'description' => 'كتب ومقاعد',
        ]);

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('تجهيز مكتبة مدرسية');

        $this->actingAs($manager)
            ->get(route('admin.donations.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertSee('تجهيز مكتبة مدرسية');

        $this->actingAs($manager)
            ->get(route('admin.donations.edit', $donation))
            ->assertOk()
            ->assertSee('تجهيز مكتبة مدرسية');
    }

    public function test_admin_can_record_and_update_an_other_donation(): void
    {
        $mosque = $this->mosque();
        $manager = $this->manager($mosque);

        $this->actingAs($manager)
            ->post(route('admin.donations.store'), [
                'type' => 'other',
                'custom_type' => 'خدمة تنظيف شهرية',
                'status' => 'accepted',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'تنظيف الجامع',
            ])
            ->assertRedirect(route('admin.donations.index'));

        $donation = Donation::where('title', 'تنظيف الجامع')->firstOrFail();
        $this->assertSame('other', $donation->type->value);
        $this->assertSame('خدمة تنظيف شهرية', $donation->custom_type);

        $this->actingAs($manager)
            ->patch(route('admin.donations.update', $donation), [
                'type' => 'other',
                'custom_type' => 'خدمة صيانة شهرية',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'صيانة الجامع',
            ])
            ->assertRedirect(route('admin.donations.index'));

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'custom_type' => 'خدمة صيانة شهرية',
        ]);

        $this->actingAs($manager)
            ->patch(route('admin.donations.update', $donation), [
                'type' => 'moral',
                'custom_type' => 'يجب تجاهله',
                'donor_name' => 'فاعل خير',
                'donor_phone' => '0999999999',
                'delivery_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'title' => 'صيانة الجامع',
            ])
            ->assertRedirect(route('admin.donations.index'));

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'type' => 'moral',
            'custom_type' => null,
        ]);
    }
}
