<?php

namespace Tests\Feature\Module1;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoginReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create();
    }

    private function log(User $user, string $when, string $ip = '10.0.0.1'): UserLoginLog
    {
        return $user->loginLogs()->create([
            'login_at' => Carbon::parse($when),
            'ip_address' => $ip,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Gecko/20100101 Firefox/121.0',
        ]);
    }

    public function test_only_owners_can_see_the_report_and_export(): void
    {
        $this->get(route('reports.login-logs'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->cashier()->create());

        $this->get(route('reports.login-logs'))->assertForbidden();
        $this->get(route('reports.login-logs.export'))->assertForbidden();
    }

    public function test_report_lists_logins_newest_first_with_summary(): void
    {
        $ali = User::factory()->create(['name' => 'Ali Staff']);
        $bob = User::factory()->create(['name' => 'Bob Staff']);

        $this->log($ali, '2026-11-01 09:00:00', '10.0.0.11');
        $this->log($bob, '2026-11-02 10:30:00', '10.0.0.22');
        $this->log($ali, '2026-11-03 08:15:00', '10.0.0.33');

        $this->actingAs($this->owner)
            ->get(route('reports.login-logs'))
            ->assertOk()
            ->assertSee('Ali Staff')
            ->assertSee('Bob Staff')
            ->assertSee('10.0.0.11')
            ->assertSee('Firefox')
            ->assertSee('macOS')
            ->assertSeeInOrder(['03 Nov 2026', '02 Nov 2026', '01 Nov 2026'])
            ->assertViewHas('summary', fn (array $s) => $s['total'] === 3
                && $s['staff'] === 2
                && $s['latest']->format('Y-m-d H:i') === '2026-11-03 08:15');
    }

    public function test_report_can_be_filtered_by_staff_member(): void
    {
        $ali = User::factory()->create(['name' => 'Ali Staff']);
        $bob = User::factory()->create(['name' => 'Bob Staff']);

        $this->log($ali, '2026-11-01 09:00:00', '10.0.0.11');
        $this->log($bob, '2026-11-02 10:30:00', '10.0.0.22');

        $this->actingAs($this->owner)
            ->get(route('reports.login-logs', ['user_id' => $bob->id]))
            ->assertOk()
            ->assertSee('10.0.0.22')
            ->assertDontSee('10.0.0.11')
            ->assertViewHas('summary', fn (array $s) => $s['total'] === 1);
    }

    public function test_report_can_be_filtered_by_date_range_inclusive(): void
    {
        $ali = User::factory()->create();

        $this->log($ali, '2026-11-01 23:59:00', '10.0.0.1');
        $this->log($ali, '2026-11-05 00:00:00', '10.0.0.5');
        $this->log($ali, '2026-11-07 23:30:00', '10.0.0.7');
        $this->log($ali, '2026-11-09 12:00:00', '10.0.0.9');

        $this->actingAs($this->owner)
            ->get(route('reports.login-logs', ['from' => '2026-11-05', 'to' => '2026-11-07']))
            ->assertOk()
            ->assertSee('10.0.0.5')
            ->assertSee('10.0.0.7')
            ->assertDontSee('10.0.0.1<')
            ->assertDontSee('10.0.0.9')
            ->assertViewHas('summary', fn (array $s) => $s['total'] === 2);
    }

    public function test_report_rejects_a_backwards_date_range(): void
    {
        $this->actingAs($this->owner)
            ->get(route('reports.login-logs', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_report_is_paginated(): void
    {
        $ali = User::factory()->create();

        foreach (range(1, 20) as $i) {
            $this->log($ali, "2026-10-{$i} 09:00:00");
        }

        $this->actingAs($this->owner)
            ->get(route('reports.login-logs'))
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 15 && $logs->total() === 20);
    }

    public function test_csv_export_contains_the_filtered_rows(): void
    {
        $ali = User::factory()->create(['name' => 'Ali Staff', 'username' => 'ali']);
        $bob = User::factory()->create(['name' => 'Bob Staff']);

        $this->log($ali, '2026-11-01 09:00:00', '10.0.0.11');
        $this->log($bob, '2026-11-02 10:30:00', '10.0.0.22');

        $response = $this->actingAs($this->owner)
            ->get(route('reports.login-logs.export', ['user_id' => $ali->id]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(2, $rows); // header + Ali's single login only
        $this->assertSame(
            ['No.', 'Staff Name', 'Username', 'Role', 'Login Date & Time', 'IP Address', 'Browser', 'Operating System'],
            $rows[0],
        );
        $this->assertSame(
            ['1', 'Ali Staff', 'ali', 'CASHIER', '2026-11-01 09:00:00', '10.0.0.11', 'Firefox', 'macOS'],
            $rows[1],
        );
    }

    public function test_csv_export_neutralises_spreadsheet_formulas_in_names(): void
    {
        $evil = User::factory()->create(['name' => '=HYPERLINK("http://evil.test")']);
        $this->log($evil, '2026-11-01 09:00:00');

        $rows = $this->parseCsv(
            $this->actingAs($this->owner)
                ->get(route('reports.login-logs.export'))
                ->streamedContent()
        );

        // The name cell must start with an apostrophe, never with "=".
        $this->assertStringStartsWith("'=", $rows[1][1]);
    }

    /** @return array<int, array<int, string|null>> */
    private function parseCsv(string $csv): array
    {
        $csv = ltrim($csv, "\xEF\xBB\xBF"); // drop the UTF-8 BOM

        return array_map('str_getcsv', array_values(array_filter(explode("\n", $csv))));
    }

    public function test_log_model_parses_browser_and_platform(): void
    {
        $cases = [
            ['Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537 Edg/120', 'Microsoft Edge', 'Windows'],
            ['Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537', 'Chrome', 'Windows'],
            ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604', 'Safari', 'iOS'],
            ['Mozilla/5.0 (Linux; Android 14) Chrome/120 Mobile Safari/537', 'Chrome', 'Android'],
            [null, 'Unknown', 'Unknown'],
        ];

        foreach ($cases as [$agent, $browser, $platform]) {
            $log = new UserLoginLog(['user_agent' => $agent]);
            $this->assertSame($browser, $log->browser, "browser for: {$agent}");
            $this->assertSame($platform, $log->platform, "platform for: {$agent}");
        }
    }
}
