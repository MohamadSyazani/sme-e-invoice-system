<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstLoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_flagged_user_is_sent_to_the_profile_page_after_login(): void
    {
        User::factory()->mustChangePassword()->create(['username' => 'new']);

        $this->post(route('login.store'), ['username' => 'new', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('warning');
    }

    public function test_a_flagged_user_can_only_reach_the_profile_page_and_logout(): void
    {
        $user = User::factory()->mustChangePassword()->cashier()->create();

        $this->actingAs($user);

        $this->get(route('profile.edit'))->assertOk();
        $this->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->get('/')->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_flagged_owner_cannot_use_owner_pages_until_the_password_is_changed(): void
    {
        $owner = User::factory()->owner()->mustChangePassword()->create();

        $this->actingAs($owner);

        $this->get(route('staff.index'))->assertRedirect(route('profile.edit'));
        $this->get(route('reports.login-logs'))->assertRedirect(route('profile.edit'));
        $this->get(route('reports.failed-logins'))->assertRedirect(route('profile.edit'));
    }

    public function test_changing_the_password_clears_the_flag_and_unlocks_the_site(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'MyOwnPass123',
                'password_confirmation' => 'MyOwnPass123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_a_failed_password_change_keeps_the_flag(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'wrong',
                'password' => 'MyOwnPass123',
                'password_confirmation' => 'MyOwnPass123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    }

    public function test_a_new_staff_account_must_change_its_password(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(route('staff.store'), [
            'name' => 'Siti', 'username' => 'siti', 'role' => 'CASHIER', 'status' => 'ACTIVE',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertRedirect(route('staff.index'));

        $this->assertTrue(User::where('username', 'siti')->first()->must_change_password);
    }

    public function test_an_owner_resetting_a_password_forces_a_change_but_other_edits_do_not(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create();
        $payload = ['name' => $staff->name, 'username' => $staff->username, 'role' => 'CASHIER', 'status' => 'ACTIVE'];

        $this->actingAs($owner);

        // Just renaming: no flag.
        $this->put(route('staff.update', $staff), ['name' => 'Renamed'] + $payload);
        $this->assertFalse($staff->fresh()->must_change_password);

        // Resetting the password: flag.
        $this->put(route('staff.update', $staff), $payload + ['password' => 'Reset12345', 'password_confirmation' => 'Reset12345']);
        $this->assertTrue($staff->fresh()->must_change_password);

        // Editing something else afterwards keeps the flag until the staff member acts.
        $this->put(route('staff.update', $staff), ['name' => 'Renamed Again'] + $payload);
        $this->assertTrue($staff->fresh()->must_change_password);
    }

    public function test_an_owner_choosing_their_own_password_is_not_flagged(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->put(route('staff.update', $owner), [
            'name' => $owner->name, 'username' => $owner->username, 'role' => 'OWNER', 'status' => 'ACTIVE',
            'password' => 'MyNewPass123', 'password_confirmation' => 'MyNewPass123',
        ])->assertRedirect(route('staff.index'));

        $this->assertFalse($owner->fresh()->must_change_password);
    }

    public function test_staff_list_shows_who_still_has_a_pending_password(): void
    {
        $owner = User::factory()->owner()->create();
        User::factory()->mustChangePassword()->create(['name' => 'Pending Pat']);

        $this->actingAs($owner)->get(route('staff.index'))->assertSee('Password pending');
    }

    public function test_seeded_default_accounts_must_change_their_public_passwords(): void
    {
        $this->seed(UserSeeder::class);

        $this->assertTrue(User::where('username', 'owner')->first()->must_change_password);
        $this->assertTrue(User::where('username', 'cashier')->first()->must_change_password);
    }
}
