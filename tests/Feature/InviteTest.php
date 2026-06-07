<?php

namespace Tests\Feature;

use App\Models\AccountInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_link_creates_pro_account_for_one_year(): void
    {
        $invite = AccountInvite::generate(365, 30);

        $this->get(route('invite.show', $invite->token))
            ->assertOk()
            ->assertSee('Te han invitado', false);

        $this->post(route('invite.register', $invite->token), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('documents.index'));

        $this->assertAuthenticated();

        $user = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertNotNull($user->pro_until);
        $this->assertTrue($user->pro_until->greaterThan(now()->addDays(360)));
        $this->assertFalse($user->is_admin);

        $invite->refresh();
        $this->assertNotNull($invite->used_at);
        $this->assertSame($user->id, $invite->used_by);
    }

    public function test_invite_is_single_use(): void
    {
        $invite = AccountInvite::generate();
        $invite->update(['used_at' => now()]);

        $this->get(route('invite.show', $invite->token))
            ->assertOk()
            ->assertSee('no válida', false);

        $this->post(route('invite.register', $invite->token), [
            'name' => 'X', 'email' => 'x@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertStatus(410);
    }

    public function test_expired_pro_account_is_blocked(): void
    {
        $user = User::factory()->create(['pro_until' => now()->subDay()]);

        $this->actingAs($user)->get(route('documents.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_only_admins_generate_invites(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('invites.store'))->assertRedirect();
        $this->assertDatabaseCount('account_invites', 1);

        $plain = User::factory()->create(['is_admin' => false]);
        $this->actingAs($plain)->post(route('invites.store'))->assertForbidden();
    }
}
