<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_shown_to_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_logged_in_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_active_user_can_log_in_and_the_login_is_audited(): void
    {
        $user = User::factory()->create(['username' => 'amin']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36'])
            ->post(route('login.store'), ['username' => 'amin', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('user_login_logs', 1);
        $this->assertDatabaseHas('user_login_logs', [
            'user_id' => $user->id,
            'ip_address' => '203.0.113.9',
        ]);

        $log = $user->loginLogs()->first();
        $this->assertSame('Chrome', $log->browser);
        $this->assertSame('Windows', $log->platform);
        $this->assertNotNull($log->login_at);
    }

    public function test_every_successful_login_adds_a_new_log_row(): void
    {
        $user = User::factory()->create(['username' => 'amin']);

        foreach (range(1, 3) as $ignored) {
            $this->post(route('login.store'), ['username' => 'amin', 'password' => 'password']);
            $this->post(route('logout'));
        }

        $this->assertSame(3, $user->loginLogs()->count());
    }

    public function test_wrong_password_is_rejected_and_not_logged(): void
    {
        User::factory()->create(['username' => 'amin']);

        $this->post(route('login.store'), ['username' => 'amin', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseCount('user_login_logs', 0);
    }

    public function test_unknown_username_is_rejected(): void
    {
        $this->post(route('login.store'), ['username' => 'nobody', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in_even_with_the_correct_password(): void
    {
        User::factory()->inactive()->create(['username' => 'resigned']);

        $this->post(route('login.store'), ['username' => 'resigned', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Your account is inactive. Please contact the shop owner.']);

        $this->assertGuest();
        $this->assertDatabaseCount('user_login_logs', 0);
    }

    public function test_user_deactivated_mid_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->update(['status' => User::STATUS_INACTIVE]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_cashier_dashboard_contains_no_owner_data_or_staff_links(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Active staff')
            ->assertDontSee('Recent logins')
            ->assertDontSee(route('staff.index'))
            ->assertDontSee(route('reports.login-logs'));
    }

    public function test_owner_dashboard_shows_staff_numbers_and_links(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->inactive()->create();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Active staff')
            ->assertSee('Inactive staff')
            ->assertSee(route('staff.index'))
            ->assertSee(route('reports.login-logs'));
    }
}
