<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

/**
 * صفحة الرسائل: تجميع المحادثات حسب الطرف الآخر، تعليم المقروء عند فتح
 * المحادثة فقط، الإرسال، البحث، والعزل بين الجامعات.
 */
class MessageConversationsTest extends TestCase
{
    private function mosque(): Tenant
    {
        $mosque = Tenant::factory()->create();
        config(['app.current_tenant_id' => $mosque->id]);
        app(RoleService::class)->provisionTenantRoles($mosque);

        return $mosque;
    }

    private function teacher(Tenant $mosque, string $name): User
    {
        return User::factory()->for($mosque)->create(['name' => $name]);
    }

    /** @param array<string, mixed> $overrides */
    private function message(Tenant $mosque, User $sender, User $recipient, string $body, array $overrides = []): Message
    {
        return Message::create(array_merge([
            'tenant_id' => $mosque->id,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'body' => $body,
        ], $overrides));
    }

    public function test_conversations_are_grouped_by_partner_with_the_last_message(): void
    {
        $mosque = $this->mosque();
        $me = $this->teacher($mosque, 'المعلم');
        $admin = User::factory()->admin()->for($mosque)->create(['name' => 'المدير']);
        $colleague = $this->teacher($mosque, 'الزميل');

        $this->message($mosque, $admin, $me, 'رسالة أولى', ['read_at' => now()]);
        $this->message($mosque, $admin, $me, 'رسالة ثانية');
        $this->message($mosque, $me, $colleague, 'أهلاً زميلي');

        $this->actingAs($me)->get(route('teacher.messages.index'))
            ->assertOk()
            ->assertSee('المدير')
            ->assertSee('الزميل')
            ->assertSee('رسالة ثانية')
            ->assertSee('أهلاً زميلي')
            ->assertSee('أنت:');
    }

    public function test_opening_a_thread_marks_only_that_partners_messages_as_read(): void
    {
        $mosque = $this->mosque();
        $me = $this->teacher($mosque, 'المعلم');
        $admin = User::factory()->admin()->for($mosque)->create(['name' => 'المدير']);
        $colleague = $this->teacher($mosque, 'الزميل');

        $fromAdmin = $this->message($mosque, $admin, $me, 'من المدير');
        $fromColleague = $this->message($mosque, $colleague, $me, 'من الزميل');

        $this->actingAs($me)
            ->get(route('teacher.messages.index', ['with' => $admin->id]))
            ->assertOk()
            ->assertSee('من المدير');

        $this->assertNotNull($fromAdmin->fresh()->read_at);
        $this->assertNull($fromColleague->fresh()->read_at);
    }

    public function test_sending_a_message_opens_its_thread(): void
    {
        $mosque = $this->mosque();
        $me = $this->teacher($mosque, 'المعلم');
        $admin = User::factory()->admin()->for($mosque)->create(['name' => 'المدير']);

        $this->actingAs($me)->post(route('teacher.messages.store'), [
            'recipient_id' => $admin->id,
            'subject' => 'استفسار',
            'body' => 'السلام عليكم',
        ])->assertRedirect(route('teacher.messages.index', ['with' => $admin->id]));

        $this->assertDatabaseHas('messages', [
            'sender_id' => $me->id,
            'recipient_id' => $admin->id,
            'subject' => 'استفسار',
        ]);
    }

    public function test_search_filters_the_conversation_list(): void
    {
        $mosque = $this->mosque();
        $me = $this->teacher($mosque, 'المعلم');
        $admin = User::factory()->admin()->for($mosque)->create(['name' => 'المدير']);
        $colleague = $this->teacher($mosque, 'الزميل');

        $this->message($mosque, $admin, $me, 'رسالة المدير');
        $this->message($mosque, $colleague, $me, 'رسالة الزميل');

        $this->actingAs($me)->get(route('teacher.messages.index', ['q' => 'زميل']))
            ->assertOk()
            ->assertSee('رسالة الزميل')
            ->assertDontSee('رسالة المدير');
    }

    public function test_a_thread_cannot_be_opened_with_self_or_another_mosque_user(): void
    {
        $mosque = $this->mosque();
        $me = $this->teacher($mosque, 'المعلم');

        $otherMosque = Tenant::factory()->create();
        $stranger = User::factory()->for($otherMosque)->create();

        $this->actingAs($me)
            ->get(route('teacher.messages.index', ['with' => $me->id]))
            ->assertNotFound();

        $this->actingAs($me)
            ->get(route('teacher.messages.index', ['with' => $stranger->id]))
            ->assertNotFound();
    }
}
