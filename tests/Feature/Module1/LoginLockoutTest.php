<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    private function failLogin(string $username, int $times, ?string $ip = null): void
    {
        foreach (range(1, $times) as $ignored) {
            $request = $ip ? $this->withServerVariables(['REMOTE_ADDR' => $ip]) : $this;
            $request->post(route('login.store'), ['username' => $username, 'password' => 'bad']);
        }
    }

    public function test_five_failed_attempts_lock_the_form_with_a_countdown_instead_of_a_429_page(): void
    {
        User::factory()->create(['username' => 'a']);

        // Attempts 1-4: the normal "credentials do not match" message.
        foreach (range(1, 4) as $ignored) {
            $this->post(route('login.store'), ['username' => 'a', 'password' => 'bad'])
                ->assertRedirect()
                ->assertSessionHasErrors('username');
        }

        // Attempt 5 starts the lockout: back to the login page, no error page.
        $this->post(route('login.store'), ['username' => 'a', 'password' => 'bad'])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        // Every one of the 5 failures was still audited.
        $this->assertDatabaseCount('failed_login_attempts', 5);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Too many attempts, please wait')
            ->assertSee('01:00')
            ->assertSee('id="login-button"', false)
            ->assertSee('disabled', false);
    }

    public function test_correct_credentials_are_refused_while_locked_out_and_work_after_the_minute(): void
    {
        $user = User::factory()->create(['username' => 'a']);

        $this->failLogin('a', 5);

        // Right password, but still locked: no login, no new audit rows.
        $this->post(route('login.store'), ['username' => 'a', 'password' => 'password'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('failed_login_attempts', 5);
        $this->assertDatabaseCount('user_login_logs', 0);

        // The countdown has moved on, and the lock survives a page refresh.
        $this->travel(30)->seconds();
        $this->get(route('login'))->assertSee('00:30');

        // After the minute the form works again.
        $this->travel(31)->seconds();
        $this->get(route('login'))->assertDontSee('Too many attempts');

        $this->post(route('login.store'), ['username' => 'a', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_lock_belongs_to_the_username_not_to_the_ip_address(): void
    {
        User::factory()->create(['username' => 'ali']);
        $siti = User::factory()->create(['username' => 'siti']);

        $this->failLogin('ali', 5);

        // A different person on the SAME computer / Wi-Fi is not affected.
        $this->post(route('login.store'), ['username' => 'siti', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($siti);
    }

    public function test_the_locked_username_stays_locked_from_another_ip_address(): void
    {
        User::factory()->create(['username' => 'ali']);

        $this->failLogin('ali', 5, '203.0.113.10');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post(route('login.store'), ['username' => 'ali', 'password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_same_username_in_a_different_case_shares_one_counter(): void
    {
        User::factory()->create(['username' => 'ali']);

        $this->post(route('login.store'), ['username' => 'ali', 'password' => 'bad']);
        $this->post(route('login.store'), ['username' => 'ALI', 'password' => 'bad']);
        $this->post(route('login.store'), ['username' => ' ali', 'password' => 'bad']);
        $this->post(route('login.store'), ['username' => 'Ali', 'password' => 'bad']);
        $this->post(route('login.store'), ['username' => 'ali', 'password' => 'bad'])
            ->assertRedirect(route('login'))
            ->assertSessionHasNoErrors();

        $this->get(route('login'))->assertSee('Too many attempts, please wait');
    }

    public function test_an_unregistered_username_is_locked_in_the_same_way(): void
    {
        $this->failLogin('ghost', 5);

        $this->get(route('login'))->assertSee('Too many attempts, please wait');
    }

    public function test_submitting_a_different_username_clears_the_remembered_lock(): void
    {
        User::factory()->create(['username' => 'ali']);
        User::factory()->create(['username' => 'siti']);

        $this->failLogin('ali', 5);
        $this->get(route('login'))->assertSee('Too many attempts');

        $this->post(route('login.store'), ['username' => 'siti', 'password' => 'bad'])
            ->assertSessionHasErrors('username');

        $this->get(route('login'))->assertDontSee('Too many attempts');
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        User::factory()->create(['username' => 'a']);

        $this->failLogin('a', 4);

        $this->post(route('login.store'), ['username' => 'a', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        // Four more bad attempts must not lock the form (the counter started again from zero).
        foreach (range(1, 4) as $ignored) {
            $this->post(route('login.store'), ['username' => 'a', 'password' => 'bad'])
                ->assertSessionHasErrors('username');
        }

        $this->get(route('login'))->assertDontSee('Too many attempts');
    }

    public function test_the_login_page_has_no_lockout_message_normally(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Too many attempts');
    }
}
