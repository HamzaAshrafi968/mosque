<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    public function test_dashboard_returns_cached_stats(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        Student::factory()->count(3)->create(['tenant_id' => $tenant->id, 'gender' => 'male']);
        Student::factory()->count(2)->create(['tenant_id' => $tenant->id, 'gender' => 'female']);
        Teacher::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('stats.students_count', 5)
            ->assertJsonPath('stats.male_students_count', 3)
            ->assertJsonPath('stats.female_students_count', 2)
            ->assertJsonPath('stats.teachers_count', 1);

        $this->assertTrue(Cache::has(DashboardService::key($tenant->id, 'dashboard_stats')));
    }

    public function test_stats_cache_is_flushed_when_student_created(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->admin()->for($tenant)->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')->assertJsonPath('stats.students_count', 0);

        $this->postJson('/api/admin/students', [
            'name' => 'طالب',
            'gender' => 'male',
        ])->assertCreated();

        $this->getJson('/api/admin/dashboard')->assertJsonPath('stats.students_count', 1);
    }
}
