<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->put(route('profile.update'), ['name' => 'X'])->assertRedirect(route('login'));
        $this->put(route('profile.password'), [])->assertRedirect(route('login'));
    }

    public function test_cashier_and_owner_can_open_their_profile(): void
    {
        foreach ([User::factory()->cashier()->create(['name' => 'Cass Cashier']), User::factory()->owner()->create(['name' => 'Olly Owner'])] as $user) {
            $this->actingAs($user)
                ->get(route('profile.edit'))
                ->assertOk()
                ->assertSee($user->name)
                ->assertSee($user->username);
        }
    }

    public function test_user_can_change_their_own_name(): void
    {
        $user = User::factory()->cashier()->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'New Name'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create(['name' => 'Keep Me']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('Keep Me', $user->fresh()->name);
    }

    public function test_a_cashier_cannot_promote_themselves_or_change_username_through_the_profile(): void
    {
        $cashier = User::factory()->cashier()->create(['username' => 'cass']);

        $this->actingAs($cashier)->put(route('profile.update'), [
            'name' => 'Cass',
            'role' => 'OWNER',
            'status' => 'ACTIVE',
            'username' => 'hacker',
        ])->assertRedirect(route('profile.edit'));

        $cashier->refresh();
        $this->assertSame('CASHIER', $cashier->role);
        $this->assertSame('cass', $cashier->username);
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(); // password is "password"

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('BrandNew123', $user->fresh()->password));
    }

    public function test_changing_the_password_requires_the_correct_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'not-my-password',
                'password' => 'BrandNew123',
                'password_confirmation' => 'BrandNew123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_must_be_strong_confirmed_and_different(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'short1', 'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');

        $this->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'BrandNew123', 'password_confirmation' => 'Mismatch123',
        ])->assertSessionHasErrors('password');

        $this->put(route('profile.password'), [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_navbar_links_to_the_profile(): void
    {
        $this->actingAs(User::factory()->cashier()->create())
            ->get(route('dashboard'))
            ->assertSee(route('profile.edit'));
    }
}
