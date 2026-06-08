<?php

namespace Tests\Feature;

use App\Models\PageVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_visit_is_tracked(): void
    {
        $this->get('/')->assertOk();

        $this->assertDatabaseHas('page_visits', ['page_type' => 'home']);
    }

    public function test_bots_are_not_tracked(): void
    {
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();

        $this->assertDatabaseCount('page_visits', 0);
    }

    public function test_visits_panel_is_admin_only(): void
    {
        PageVisit::create(['ip' => '8.8.8.8', 'url' => 'https://x/', 'page_type' => 'home', 'visited_at' => now()]);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.visits'))->assertOk()->assertSee('Visitas');

        $plain = User::factory()->create(['is_admin' => false]);
        $this->actingAs($plain)->get(route('admin.visits'))->assertForbidden();
    }
}
