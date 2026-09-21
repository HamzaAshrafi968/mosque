<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    private function mosque(array $attributes = []): Tenant
    {
        return Tenant::factory()->create($attributes);
    }

    public function test_home_page_is_public_and_shows_the_foundation_name(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee(config('site.name'))
            ->assertSee(config('site.tagline'))
            ->assertSee('<meta name="description"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_home_page_redirects_authenticated_users_to_their_dashboard(): void
    {
        $tenant = $this->mosque();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();

        $this->actingAs($admin)->get(route('site.home'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_home_page_lists_public_mosques_only(): void
    {
        $this->mosque(['name' => 'جامع النور', 'code' => 'noor']);
        $this->mosque(['name' => 'جامع الأقصى', 'code' => 'aqsa', 'status' => Tenant::STATUS_ARCHIVED, 'is_active' => false]);
        $this->mosque(['name' => 'جامع بلا رمز', 'code' => null]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('جامع النور')
            ->assertDontSee('جامع الأقصى')
            ->assertDontSee('جامع بلا رمز');
    }

    public function test_mosques_index_lists_only_public_mosques(): void
    {
        $this->mosque(['name' => 'جامع الفرقان', 'code' => 'furqan']);
        $this->mosque(['name' => 'جامع قديم', 'code' => 'qadeem', 'status' => Tenant::STATUS_ARCHIVED, 'is_active' => false]);

        $this->get(route('site.mosques.index'))
            ->assertOk()
            ->assertSee('جامع الفرقان')
            ->assertDontSee('جامع قديم');
    }

    public function test_mosque_page_shows_public_details_and_structured_data(): void
    {
        $mosque = $this->mosque([
            'name' => 'جامع الرحمة',
            'code' => 'rahma',
            'description' => 'جامع الرحمة يضم حلقات تحفيظ القرآن الكريم.',
            'address' => 'شارع الرئيسي',
            'phone' => '+970000000',
            'map_url' => 'https://maps.example.com/rahma',
        ]);

        $this->get(route('site.mosques.show', $mosque))
            ->assertOk()
            ->assertSee('جامع الرحمة')
            ->assertSee('جامع الرحمة يضم حلقات تحفيظ القرآن الكريم.')
            ->assertSee('شارع الرئيسي')
            ->assertSee('https://maps.example.com/rahma', false)
            ->assertSee('"@type":"Mosque"', false);
    }

    public function test_suspended_mosques_are_not_public(): void
    {
        $archived = $this->mosque(['code' => 'old', 'status' => Tenant::STATUS_ARCHIVED, 'is_active' => false]);
        $inactive = $this->mosque(['code' => 'stopped', 'status' => Tenant::STATUS_INACTIVE, 'is_active' => false]);

        $this->get(route('site.mosques.show', $archived))->assertNotFound();
        $this->get(route('site.mosques.show', $inactive))->assertNotFound();
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $mosque = $this->mosque(['code' => 'salam']);
        $archived = $this->mosque(['code' => 'old', 'status' => Tenant::STATUS_ARCHIVED, 'is_active' => false]);

        $this->get(route('site.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('site.home'), false)
            ->assertSee(route('site.mosques.index'), false)
            ->assertSee(route('site.mosques.show', $mosque), false)
            ->assertDontSee(route('site.mosques.show', $archived), false);
    }

    public function test_robots_txt_disallows_private_areas_and_points_to_the_sitemap(): void
    {
        $this->get(route('site.robots'))
            ->assertOk()
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /teacher', false)
            ->assertSee('Disallow: /student', false)
            ->assertSee('Disallow: /guardian', false)
            ->assertSee('Disallow: /super-admin', false)
            ->assertSee('Disallow: /api/', false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_login_page_and_authenticated_portals_are_noindex(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $tenant = $this->mosque();
        config(['app.current_tenant_id' => $tenant->id]);
        $admin = User::factory()->admin()->for($tenant)->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_public_pages_are_cacheable_for_crawlers(): void
    {
        $response = $this->get(route('site.home'));

        $response->assertOk();
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
