<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentAcademicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AudioAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@mosque.test')->firstOrFail();
    }

    private function audio(string $name = 'lesson.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 512, 'audio/mpeg');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'درس صوتي',
            'body' => '',
            'audience' => 'all',
        ], $overrides);
    }

    public function test_manager_can_publish_audio_announcement_with_weekly_expiry(): void
    {
        Storage::fake('public');
        $this->travelTo('2026-09-15 10:00:00');

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'audio' => $this->audio(),
            ]))
            ->assertSessionHas('success');

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'درس صوتي')
            ->firstOrFail();

        $this->assertTrue($announcement->hasAudio());
        $this->assertSame('lesson.mp3', $announcement->audio_original_name);
        Storage::disk('public')->assertExists($announcement->audio_path);
        $this->assertSame('2026-09-22 10:00:00', $announcement->expires_at->toDateTimeString());
        $this->assertStringContainsString($announcement->audio_path, $announcement->audioUrl());
    }

    public function test_audio_announcement_can_opt_out_of_auto_delete(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'audio' => $this->audio('keep.mp3'),
                'auto_delete' => '0',
            ]))
            ->assertSessionHas('success');

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'درس صوتي')
            ->firstOrFail();

        $this->assertTrue($announcement->hasAudio());
        $this->assertNull($announcement->expires_at);
    }

    public function test_announcement_requires_body_or_audio(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload())
            ->assertSessionHasErrors('body');
    }

    public function test_non_audio_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'audio' => UploadedFile::fake()->create('document.pdf', 64, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('audio');
    }

    public function test_expired_audio_announcements_are_purged_with_their_files(): void
    {
        Storage::fake('public');
        $this->travelTo('2026-09-15 10:00:00');

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'audio' => $this->audio('expired.mp3'),
            ]))
            ->assertSessionHas('success');

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'درس صوتي')
            ->firstOrFail();
        $path = $announcement->audio_path;

        $this->travel(8)->days();

        $this->artisan('announcements:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_purge_keeps_active_and_text_only_announcements(): void
    {
        Storage::fake('public');

        $this->travelTo('2026-09-15 10:00:00');
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'title' => 'صوتي منتهي',
                'audio' => $this->audio('old.mp3'),
            ]))
            ->assertSessionHas('success');

        $this->travelTo('2026-09-18 10:00:00');
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'title' => 'صوتي نشط',
                'audio' => $this->audio('new.mp3'),
            ]))
            ->assertSessionHas('success');
        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'title' => 'إعلان نصي',
                'body' => 'يبقى للأبد',
            ]))
            ->assertSessionHas('success');

        $this->travelTo('2026-09-23 10:00:00');
        $this->artisan('announcements:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('announcements', ['title' => 'صوتي منتهي']);
        $this->assertDatabaseHas('announcements', ['title' => 'صوتي نشط']);
        $this->assertDatabaseHas('announcements', ['title' => 'إعلان نصي']);
    }

    public function test_deleting_announcement_removes_audio_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.announcements.store'), $this->payload([
                'audio' => $this->audio(),
            ]))
            ->assertSessionHas('success');

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'درس صوتي')
            ->firstOrFail();
        $path = $announcement->audio_path;

        $this->actingAs($this->admin())
            ->delete(route('admin.announcements.destroy', $announcement))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_expired_announcements_are_hidden_from_student_portal(): void
    {
        $student = Student::query()->firstOrFail();

        Announcement::create([
            'tenant_id' => $student->tenant_id,
            'title' => 'قديم',
            'body' => 'منتهي',
            'audience' => 'all',
            'published_at' => now()->subWeek(),
            'expires_at' => now()->subMinute(),
        ]);

        Announcement::create([
            'tenant_id' => $student->tenant_id,
            'title' => 'حالي',
            'body' => 'نشط',
            'audience' => 'all',
            'published_at' => now(),
        ]);

        $announcements = app(StudentAcademicService::class)->announcements($student);

        $this->assertFalse($announcements->contains('title', 'قديم'));
        $this->assertTrue($announcements->contains('title', 'حالي'));
    }

    public function test_api_manager_can_publish_audio_announcement(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $response = $this->post('/api/v1/admin/announcements', $this->payload([
            'title' => 'إعلان صوتي API',
            'audio' => $this->audio('api.mp3'),
        ]));

        $response->assertCreated()->assertJsonPath('data.has_audio', true);

        $announcement = Announcement::withoutGlobalScope('tenant')
            ->where('title', 'إعلان صوتي API')
            ->firstOrFail();

        $this->assertNotNull($announcement->expires_at);
        $this->assertStringContainsString($announcement->audio_path, $response->json('data.audio_url'));
    }
}
