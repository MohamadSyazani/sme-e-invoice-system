<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Boss Owner']);
    }

    /** @return array<string, string> */
    private function validStaff(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Siti Cashier',
            'username' => 'siti',
            'role' => 'CASHIER',
            'status' => 'ACTIVE',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ], $overrides);
    }

    // ---------- access control ----------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));
        $this->post(route('staff.store'), $this->validStaff())->assertRedirect(route('login'));
    }

    public function test_cashiers_get_403_on_every_staff_route(): void
    {
        $cashier = User::factory()->cashier()->create();
        $other = User::factory()->cashier()->create();

        $this->actingAs($cashier);

        $this->get(route('staff.index'))->assertForbidden();
        $this->get(route('staff.create'))->assertForbidden();
        $this->post(route('staff.store'), $this->validStaff())->assertForbidden();
        $this->get(route('staff.edit', $other))->assertForbidden();
        $this->put(route('staff.update', $other), $this->validStaff())->assertForbidden();
        $this->patch(route('staff.toggle-status', $other))->assertForbidden();
        $this->delete(route('staff.destroy', $other))->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseMissing('users', ['username' => 'siti']);
    }

    // ---------- read ----------

    public function test_owner_can_list_and_search_staff(): void
    {
        User::factory()->create(['name' => 'Ahmad Cashier', 'username' => 'ahmad']);
        User::factory()->create(['name' => 'Lim Cashier', 'username' => 'lim']);

        $this->actingAs($this->owner)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Ahmad Cashier')
            ->assertSee('Lim Cashier');

        $this->get(route('staff.index', ['q' => 'ahmad']))
            ->assertOk()
            ->assertSee('Ahmad Cashier')
            ->assertDontSee('Lim Cashier');
    }

    public function test_owner_can_filter_staff_by_status(): void
    {
        User::factory()->create(['name' => 'Working Wendy']);
        User::factory()->inactive()->create(['name' => 'Resigned Rita']);

        $this->actingAs($this->owner)
            ->get(route('staff.index', ['status' => 'INACTIVE']))
            ->assertSee('Resigned Rita')
            ->assertDontSee('Working Wendy');
    }

    // ---------- create ----------

    public function test_owner_can_create_a_staff_account_with_a_hashed_password(): void
    {
        $this->actingAs($this->owner)
            ->post(route('staff.store'), $this->validStaff())
            ->assertRedirect(route('staff.index'));

        $staff = User::where('username', 'siti')->firstOrFail();

        $this->assertSame('CASHIER', $staff->role);
        $this->assertSame('ACTIVE', $staff->status);
        $this->assertNotSame('Secret123', $staff->password);
        $this->assertTrue(Hash::check('Secret123', $staff->password));
    }

    public function test_owner_can_create_another_owner(): void
    {
        $this->actingAs($this->owner)
            ->post(route('staff.store'), $this->validStaff(['username' => 'co', 'role' => 'OWNER']))
            ->assertRedirect(route('staff.index'));

        $this->assertTrue(User::where('username', 'co')->first()->isOwner());
    }

    public function test_create_is_validated(): void
    {
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($this->owner);

        $this->post(route('staff.store'), $this->validStaff(['username' => 'taken']))
            ->assertSessionHasErrors('username');

        $this->post(route('staff.store'), $this->validStaff(['password' => 'short1', 'password_confirmation' => 'short1']))
            ->assertSessionHasErrors('password');

        $this->post(route('staff.store'), $this->validStaff(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))
            ->assertSessionHasErrors('password');

        $this->post(route('staff.store'), $this->validStaff(['password_confirmation' => 'Different123']))
            ->assertSessionHasErrors('password');

        $this->post(route('staff.store'), $this->validStaff(['role' => 'SUPERADMIN']))
            ->assertSessionHasErrors('role');

        $this->post(route('staff.store'), $this->validStaff(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['name' => 'Siti Cashier']);
    }

    // ---------- update ----------

    public function test_owner_can_update_staff_without_changing_the_password(): void
    {
        $staff = User::factory()->create(['username' => 'old']);
        $oldHash = $staff->password;

        $this->actingAs($this->owner)
            ->put(route('staff.update', $staff), [
                'name' => 'Renamed Staff',
                'username' => 'new',
                'role' => 'CASHIER',
                'status' => 'ACTIVE',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('staff.index'));

        $staff->refresh();
        $this->assertSame('Renamed Staff', $staff->name);
        $this->assertSame('new', $staff->username);
        $this->assertSame($oldHash, $staff->password);
    }

    public function test_owner_can_reset_a_staff_password(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('staff.update', $staff), [
                'name' => $staff->name,
                'username' => $staff->username,
                'role' => 'CASHIER',
                'status' => 'ACTIVE',
                'password' => 'BrandNew999',
                'password_confirmation' => 'BrandNew999',
            ])
            ->assertRedirect(route('staff.index'));

        $this->assertTrue(Hash::check('BrandNew999', $staff->fresh()->password));
    }

    public function test_update_allows_keeping_the_same_username_but_not_stealing_anothers(): void
    {
        $staff = User::factory()->create(['username' => 'mine']);
        User::factory()->create(['username' => 'theirs']);

        $this->actingAs($this->owner);

        $payload = ['name' => 'X', 'role' => 'CASHIER', 'status' => 'ACTIVE'];

        $this->put(route('staff.update', $staff), $payload + ['username' => 'mine'])
            ->assertSessionHasNoErrors();

        $this->put(route('staff.update', $staff), $payload + ['username' => 'theirs'])
            ->assertSessionHasErrors('username');
    }

    public function test_owner_can_promote_a_cashier_to_owner(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->owner)
            ->put(route('staff.update', $staff), [
                'name' => $staff->name,
                'username' => $staff->username,
                'role' => 'OWNER',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('staff.index'));

        $this->assertTrue($staff->fresh()->isOwner());
    }

    public function test_owner_cannot_change_their_own_role_or_status(): void
    {
        $this->actingAs($this->owner)
            ->put(route('staff.update', $this->owner), [
                'name' => $this->owner->name,
                'username' => $this->owner->username,
                'role' => 'CASHIER',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('role');

        $this->put(route('staff.update', $this->owner), [
            'name' => $this->owner->name,
            'username' => $this->owner->username,
            'role' => 'OWNER',
            'status' => 'INACTIVE',
        ])->assertSessionHasErrors('role');

        $this->owner->refresh();
        $this->assertSame('OWNER', $this->owner->role);
        $this->assertSame('ACTIVE', $this->owner->status);
    }

    public function test_owner_can_still_edit_their_own_name(): void
    {
        $this->actingAs($this->owner)
            ->put(route('staff.update', $this->owner), [
                'name' => 'Boss Renamed',
                'username' => $this->owner->username,
                'role' => 'OWNER',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('staff.index'));

        $this->assertSame('Boss Renamed', $this->owner->fresh()->name);
    }

    // ---------- activate / deactivate ----------

    public function test_owner_can_deactivate_and_reactivate_staff(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->owner);

        $this->patch(route('staff.toggle-status', $staff))->assertSessionHas('success');
        $this->assertSame('INACTIVE', $staff->fresh()->status);

        $this->patch(route('staff.toggle-status', $staff))->assertSessionHas('success');
        $this->assertSame('ACTIVE', $staff->fresh()->status);
    }

    public function test_owner_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('staff.toggle-status', $this->owner))
            ->assertSessionHas('error');

        $this->assertSame('ACTIVE', $this->owner->fresh()->status);
    }

    // ---------- delete ----------

    public function test_owner_can_delete_an_account_that_has_no_history(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($this->owner)
            ->delete(route('staff.destroy', $staff))
            ->assertRedirect(route('staff.index'));

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_an_account_with_login_history_cannot_be_deleted(): void
    {
        $staff = User::factory()->create();
        $staff->loginLogs()->create(['login_at' => now(), 'ip_address' => '127.0.0.1', 'user_agent' => 'test']);

        $this->actingAs($this->owner)
            ->delete(route('staff.destroy', $staff))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseCount('user_login_logs', 1);
    }

    public function test_owner_cannot_delete_themselves(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('staff.destroy', $this->owner))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->owner->id]);
    }
}
