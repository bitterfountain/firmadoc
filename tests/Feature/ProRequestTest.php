<?php

namespace Tests\Feature;

use App\Models\ProRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_request_pro_access(): void
    {
        Mail::fake();

        $this->post(route('pro.request.store'), [
            'email' => 'quiero@example.com',
            'name' => 'Quiero Pro',
            'message' => 'Para firmar contratos',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('pro_requests', ['email' => 'quiero@example.com', 'status' => 'pending']);
    }

    public function test_admin_invites_a_request(): void
    {
        Mail::fake();
        $req = ProRequest::create(['email' => 'quiero@example.com', 'status' => 'pending']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('pro.request.invite', $req))->assertRedirect();

        $this->assertSame('invited', $req->fresh()->status);
        $this->assertDatabaseCount('account_invites', 1);
    }

    public function test_non_admin_cannot_invite(): void
    {
        $req = ProRequest::create(['email' => 'x@example.com', 'status' => 'pending']);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post(route('pro.request.invite', $req))->assertForbidden();
    }
}
