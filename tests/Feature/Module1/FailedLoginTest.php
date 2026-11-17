<?php

namespace Tests\Feature\Module1;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FailedLoginTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $reason, string $when, string $ip = '10.0.0.1', ?User $user = null, string $username = 'someone'): FailedLoginAttempt
    {
        return FailedLoginAttempt::create([
            'username' => $username,
            'user_id' => $user?->id,
            'reason' => $reason,
            'ip_address' => $ip,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537',
            'attempted_at' => Carbon::parse($when),
        ]);
    }

    // ---------- recording ----------

    public function test_a_wrong_password_is_recorded_against_the_account(): void
    {
        $user = User::factory()->create(['username' => 'amin']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Firefox/121.0'])
            ->post(route('login.store'), ['username' => 'amin', 'password' => 'TotallyWrong1'])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseCount('failed_login_attempts', 1);
        $this->assertDatabaseHas('failed_login_attempts', [
            'username' => 'amin',
            'user_id' => $user->id,
            'reason' => 'WRONG_PASSWORD',
            'ip_address' => '203.0.113.7',
        ]);
    }

    public function test_an_unknown_username_is_recorded_without_a_user(): void
    {
        $this->post(route('login.store'), ['username' => 'ghost', 'password' => 'whatever1'])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('failed_login_attempts', [
            'username' => 'ghost',
            'user_id' => null,
            'reason' => 'UNKNOWN_USERNAME',
        ]);
    }

    public function test_a_correct_password_on_an_inactive_account_is_recorded(): void
    {
        $user = User::factory()->inactive()->create(['username' => 'resigned']);

        $this->post(route('login.store'), ['username' => 'resigned', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertDatabaseHas('failed_login_attempts', [
            'username' => 'resigned',
            'user_id' => $user->id,
            'reason' => 'INACTIVE_ACCOUNT',
        ]);
    }

    public function test_the_typed_password_is_never_stored(): void
    {
        User::factory()->create(['username' => 'amin']);

        $this->post(route('login.store'), ['username' => 'amin', 'password' => 'SuperSecretGuess99']);

        $row = json_encode(\DB::table('failed_login_attempts')->first());

        $this->assertStringNotContainsString('SuperSecretGuess99', $row);
    }

    public function test_a_successful_login_records_no_failure(): void
    {
        User::factory()->create(['username' => 'amin']);

        $this->post(route('login.store'), ['username' => 'amin', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('failed_login_attempts', 0);
        $this->assertDatabaseCount('user_login_logs', 1);
    }

    public function test_deleting_a_user_keeps_their_failed_attempts_with_the_user_cleared(): void
    {
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->create(['username' => 'temp']);
        $this->attempt('WRONG_PASSWORD', '2026-11-10 09:00:00', user: $staff, username: 'temp');

        $this->actingAs($owner)->delete(route('staff.destroy', $staff))->assertRedirect(route('staff.index'));

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('failed_login_attempts', ['username' => 'temp', 'user_id' => null]);
    }

    // ---------- report ----------

    public function test_only_owners_can_open_the_failed_attempts_report(): void
    {
        $this->get(route('reports.failed-logins'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->cashier()->create())
            ->get(route('reports.failed-logins'))
            ->assertForbidden();
    }

    public function test_report_lists_attempts_newest_first_with_a_summary(): void
    {
        $owner = User::factory()->owner()->create();
        $ali = User::factory()->create(['name' => 'Ali Staff']);

        $this->attempt('WRONG_PASSWORD', '2026-11-01 09:00:00', '10.0.0.1', $ali, 'ali');
        $this->attempt('UNKNOWN_USERNAME', '2026-11-02 10:00:00', '10.0.0.2', null, 'ghost');
        $this->attempt('UNKNOWN_USERNAME', '2026-11-03 11:00:00', '10.0.0.2', null, 'ghost2');

        $this->actingAs($owner)
            ->get(route('reports.failed-logins'))
            ->assertOk()
            ->assertSee('ali')
            ->assertSee('Ali Staff')
            ->assertSee('Wrong password')
            ->assertSee('Username not registered')
            ->assertSee('Chrome')
            ->assertSeeInOrder(['03 Nov 2026', '02 Nov 2026', '01 Nov 2026'])
            ->assertViewHas('summary', fn (array $s) => $s['total'] === 3
                && $s['ips'] === 2
                && $s['unknown'] === 2
                && $s['latest']->format('Y-m-d H:i') === '2026-11-03 11:00');
    }

    public function test_report_can_be_filtered_by_reason(): void
    {
        $owner = User::factory()->owner()->create();

        $this->attempt('WRONG_PASSWORD', '2026-11-01 09:00:00', username: 'wrongpw');
        $this->attempt('UNKNOWN_USERNAME', '2026-11-02 10:00:00', username: 'ghost');

        $this->actingAs($owner)
            ->get(route('reports.failed-logins', ['reason' => 'UNKNOWN_USERNAME']))
            ->assertOk()
            ->assertSee('ghost')
            ->assertDontSee('wrongpw');
    }

    public function test_report_can_be_filtered_by_date_range(): void
    {
        $owner = User::factory()->owner()->create();

        $this->attempt('WRONG_PASSWORD', '2026-11-01 23:59:00', username: 'early');
        $this->attempt('WRONG_PASSWORD', '2026-11-05 00:00:00', username: 'inside1');
        $this->attempt('WRONG_PASSWORD', '2026-11-07 23:30:00', username: 'inside2');
        $this->attempt('WRONG_PASSWORD', '2026-11-09 12:00:00', username: 'late');

        $this->actingAs($owner)
            ->get(route('reports.failed-logins', ['from' => '2026-11-05', 'to' => '2026-11-07']))
            ->assertOk()
            ->assertSee('inside1')
            ->assertSee('inside2')
            ->assertDontSee('early')
            ->assertDontSee('late');
    }

    public function test_report_rejects_an_unknown_reason_and_a_backwards_range(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $this->get(route('reports.failed-logins', ['reason' => 'MADE_UP']))->assertSessionHasErrors('reason');
        $this->get(route('reports.failed-logins', ['from' => '2026-11-10', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
    }

    public function test_report_escapes_a_malicious_username_value(): void
    {
        $owner = User::factory()->owner()->create();
        $this->attempt('UNKNOWN_USERNAME', '2026-11-01 09:00:00', username: '"><script>alert(1)</script>');

        $this->actingAs($owner)
            ->get(route('reports.failed-logins'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ---------- dashboard card ----------

    public function test_owner_dashboard_counts_todays_failures_and_a_cashier_never_sees_them(): void
    {
        $this->attempt('WRONG_PASSWORD', now()->toDateTimeString());
        $this->attempt('WRONG_PASSWORD', now()->toDateTimeString());
        $this->attempt('WRONG_PASSWORD', now()->subDays(3)->toDateTimeString());

        $this->actingAs(User::factory()->owner()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Failed logins today')
            ->assertViewHas('stats', fn (array $s) => $s['failedToday'] === 2);

        $this->actingAs(User::factory()->cashier()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Failed logins today')
            ->assertDontSee(route('reports.failed-logins'));
    }

    public function test_report_tabs_link_both_audit_pages(): void
    {
        $this->actingAs(User::factory()->owner()->create())
            ->get(route('reports.login-logs'))
            ->assertSee(route('reports.failed-logins'));
    }
}
