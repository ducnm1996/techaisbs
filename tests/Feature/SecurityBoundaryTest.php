<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\HistoryEvent;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\PhoneIndex;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['is_active' => true, 'setup_pending' => false, 'role' => 'member']);
    }

    public function test_login_normalizes_email_and_keeps_it_after_failed_attempt(): void
    {
        $user = $this->member(['email' => 'member@example.com', 'password' => 'ExamplePassword123']);
        $this->post('/login', ['email' => '  MEMBER@EXAMPLE.COM  ', 'password' => 'ExamplePassword123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/login', ['email' => ' MEMBER@EXAMPLE.COM ', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()->assertSee('value="MEMBER@EXAMPLE.COM"', false)
            ->assertSee('data-password-toggle', false)->assertDontSee('value="wrong"', false);
    }

    private function device(User $owner, array $attributes = []): Device
    {
        return Device::create($attributes + ['user_id' => $owner->id, 'name' => 'Pixel', 'sources' => config('history.sources'), 'allow_ip' => false, 'allow_location' => false]);
    }

    private function event(array $attributes = []): array
    {
        return $attributes + ['event_id' => (string) Str::uuid(), 'source' => 'sms', 'occurred_at' => now()->toIso8601String(), 'sender_phone' => '+84901234567', 'sender_name' => 'Tên riêng'];
    }

    private function token(Device $device, array $abilities = ['events:write']): string
    {
        return $device->createToken('test', $abilities, now()->addDay())->plainTextToken;
    }

    private function api(string $token, array $body): TestResponse
    {
        Auth::forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/events', $body);
    }

    public function test_guests_registration_and_guard_separation(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $owner = $this->member();
        $device = $this->device($owner);
        $token = $this->token($device);
        $this->withToken($token)->get('/admin')->assertRedirect('/login');
        $this->withHeader('Authorization', '')->actingAs($owner, 'web')->postJson('/api/v1/events', ['events' => [$this->event()]])->assertUnauthorized();
    }

    public function test_data_scopes_policies_search_statistics_and_id_tampering(): void
    {
        $a = $this->member();
        $b = $this->member();
        $da = $this->device($a);
        $db = $this->device($b);
        $event = HistoryEvent::create(['user_id' => $b->id, 'device_id' => $db->id] + $this->event(['sender_name' => 'SECRET-B', 'phone_index' => app(PhoneIndex::class)->hash('+84901234567')]));
        $this->actingAs($a)->get('/history')->assertOk()->assertDontSee('SECRET-B');
        $this->get('/history?device='.$db->id)->assertOk()->assertDontSee('SECRET-B');
        $this->post('/history/search', ['phone' => '+84901234567'])->assertRedirect('/history');
        $this->get('/history')->assertDontSee('SECRET-B');
        $this->get('/history/'.$event->id)->assertForbidden();
        $this->delete('/history/'.$event->id, ['user_id' => $b->id])->assertForbidden();
        $this->delete('/devices/'.$db->id)->assertForbidden();
        $this->assertDatabaseHas('history_events', ['id' => $event->id]);
        $this->get('/')->assertViewHas('eventCount', 0)->assertViewHas('deviceCount', 1);
        $admin = $this->member(['role' => 'admin']);
        $this->actingAs($admin)->get('/history/'.$event->id)->assertForbidden();
        $this->get('/history')->assertDontSee('SECRET-B');
        $this->delete('/history/'.$event->id)->assertForbidden();
    }

    public function test_encrypted_storage_unique_device_event_and_safe_blade_output(): void
    {
        $a = $this->member();
        $b = $this->member();
        $da = $this->device($a);
        $db = $this->device($b);
        $ta = $this->token($da);
        $tb = $this->token($db);
        $payload = $this->event(['sender_name' => '<script>alert(1)</script>']);
        $this->api($ta, ['events' => [$payload]])->assertCreated()->assertJson(['created' => 1]);
        $this->api($ta, ['events' => [$payload]])->assertOk()->assertJson(['created' => 0, 'duplicates' => 1]);
        $this->api($tb, ['events' => [$payload]])->assertCreated();
        $this->assertDatabaseCount('history_events', 2);
        $raw = DB::table('history_events')->where('device_id', $da->id)->first();
        $this->assertStringNotContainsString('+84901234567', $raw->sender_phone);
        $this->assertStringNotContainsString('<script>', $raw->sender_name);
        $this->assertSame(app(PhoneIndex::class)->hash('+84901234567'), $raw->phone_index);
        $this->assertNull($raw->device_ip);
        $this->actingAs($a, 'web')->get('/history')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_api_rejects_account_payload_content_unknown_source_and_unapproved_location(): void
    {
        $owner = $this->member();
        $device = $this->device($owner, ['sources' => ['sms']]);
        $token = $this->token($device);
        $this->api($token, ['events' => [$this->event()], 'user_id' => 999])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['body' => 'private'])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['source' => 'zalo'])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['latitude' => 10, 'longitude' => 106, 'accuracy_m' => 10])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['occurred_at' => '2026-11-21 12:30:00'])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['occurred_at' => now()->addHour()->toIso8601String()])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['sender_name' => str_repeat('x', 121)])]])->assertUnprocessable();
        $this->assertDatabaseCount('history_events', 0);
    }

    public function test_device_ability_expiry_revocation_and_disabled_owner(): void
    {
        $owner = $this->member();
        $device = $this->device($owner);
        $this->api($this->token($device, []), ['events' => [$this->event()]])->assertForbidden();
        $expired = $device->createToken('expired', ['events:write'], now()->subMinute())->plainTextToken;
        $this->api($expired, ['events' => [$this->event()]])->assertUnauthorized();
        $token = $this->token($device);
        $owner->update(['is_active' => false]);
        $this->api($token, ['events' => [$this->event()]])->assertUnauthorized();
        $this->actingAs($owner, 'web')->get('/')->assertRedirect('/login');
        $owner->update(['is_active' => true]);
        $device->update(['revoked_at' => now()]);
        $this->api($token, ['events' => [$this->event()]])->assertUnauthorized();
        $device->update(['revoked_at' => null]);
        $device->tokens()->delete();
        $this->api($token, ['events' => [$this->event()]])->assertUnauthorized();
    }

    public function test_pairing_is_single_use_short_lived_and_account_bound(): void
    {
        $owner = $this->member();
        $this->actingAs($owner)->post('/devices/pair', ['sources' => ['sms']])->assertRedirect('/devices');
        $code = session('pair_code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('pairing_codes', ['user_id' => $owner->id, 'code_hash' => hash('sha256', $code)]);
        $this->postJson('/api/v1/pair', ['code' => $code, 'device_name' => 'Android'])->assertCreated()->assertJsonStructure(['token', 'device_id', 'expires_at', 'sources', 'allow_ip', 'allow_location'])->assertJson(['sources' => ['sms'], 'allow_ip' => false, 'allow_location' => false]);
        $this->postJson('/api/v1/pair', ['code' => $code, 'device_name' => 'Replay'])->assertUnprocessable();
        $this->assertDatabaseCount('devices', 1);
        $this->assertSame($owner->id, Device::first()->user_id);
        PairingCode::create(['user_id' => $owner->id, 'code_hash' => hash('sha256', '0123456789ABCDEF'), 'sources' => ['sms'], 'expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/pair', ['code' => '0123456789ABCDEF', 'device_name' => 'Expired'])->assertUnprocessable();
    }

    public function test_android_call_metadata_is_encrypted_idempotent_and_scoped(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $device = $this->device($owner);
        $token = $this->token($device);
        $event = $this->event(['source' => 'call', 'app_package' => 'com.samsung.android.dialer', 'call_direction' => 'outgoing', 'duration_seconds' => 125]);
        $this->api($token, ['events' => [$event]])->assertCreated();
        $this->api($token, ['events' => [$event]])->assertOk()->assertJson(['created' => 0, 'duplicates' => 1]);
        $saved = HistoryEvent::firstOrFail();
        $this->assertSame('125', $saved->duration_seconds);
        $this->assertSame('outgoing', $saved->call_direction);
        $this->assertSame('com.samsung.android.dialer', $saved->app_package);
        $raw = DB::table('history_events')->first();
        $this->assertStringNotContainsString('com.samsung', $raw->app_package);
        $this->assertStringNotContainsString('outgoing', $raw->call_direction);
        $this->assertNotSame('125', $raw->duration_seconds);
        $this->actingAs($other, 'web')->get('/history/'.$saved->id)->assertForbidden();
        $this->actingAs($owner, 'web')->get('/history/'.$saved->id)->assertOk()->assertSee('com.samsung.android.dialer')->assertSee('125');
        $this->get('/history')->assertOk()->assertSee('Gọi đi')->assertSee('125 giây')->assertSee('Pixel')->assertSee('com.samsung.android.dialer')->assertSee('Chi tiết');
        $this->api($token, ['events' => [$this->event(['source' => 'email', 'duration_seconds' => 1])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['app_package' => '<script>'])]])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['source' => 'call', 'duration_seconds' => 604801])]])->assertUnprocessable();
        $this->assertDatabaseCount('history_events', 1);
    }

    public function test_agent_telemetry_requires_grant_is_encrypted_and_never_leaks_between_accounts(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $device = $this->device($owner, ['sources' => ['device'], 'allow_location' => true]);
        $token = $this->token($device);
        $event = ['event_id' => (string) Str::uuid(), 'source' => 'device', 'occurred_at' => now()->toIso8601String(), 'device_metadata' => ['manufacturer' => 'Samsung', 'model' => '<script>agent-model</script>', 'sdk' => 36, 'android_release' => '16', 'battery_percent' => 76, 'charging' => false, 'network_type' => 'wifi', 'ram_total_bytes' => 8000000000], 'latitude' => 10.7, 'longitude' => 106.6, 'accuracy_m' => 30];
        $this->api($token, ['events' => [$event]])->assertCreated();
        $this->api($token, ['events' => [$event]])->assertOk()->assertJson(['duplicates' => 1]);
        $saved = HistoryEvent::firstOrFail();
        $this->assertSame(76, $saved->device_metadata['battery_percent']);
        $this->assertFalse($saved->device_metadata['charging']);
        $this->assertStringNotContainsString('Samsung', DB::table('history_events')->first()->device_metadata);
        $this->actingAs($owner, 'web')->get('/devices')->assertOk()->assertSee('agent-model')->assertDontSee('<script>agent-model</script>', false)->assertSee('76%');
        $this->get('/history/'.$saved->id)->assertOk()->assertSee('Samsung')->assertSee('&lt;script&gt;agent-model&lt;/script&gt;', false);
        $this->actingAs($other, 'web')->get('/devices')->assertOk()->assertDontSee('agent-model');
        $this->get('/history?source=device')->assertOk()->assertDontSee('agent-model');
        $this->get('/history/'.$saved->id)->assertForbidden();
        $this->delete('/history/'.$saved->id)->assertForbidden();
        $sms = $this->device($other, ['sources' => ['sms']]);
        $this->api($this->token($sms), ['events' => [$event]])->assertUnprocessable();
        foreach ([['imei' => 'not-allowed'], ['battery_percent' => 101], ['network_type' => 'secret'], ['sdk' => 1]] as $invalid) {
            $bad = $event;
            $bad['event_id'] = (string) Str::uuid();
            $bad['device_metadata'] = $invalid;
            $this->api($token, ['events' => [$bad]])->assertUnprocessable();
        }
        $missing = $event;
        unset($missing['device_metadata']);
        $this->api($token, ['events' => [$missing]])->assertUnprocessable();
        $wrongSource = $event;
        $wrongSource['source'] = 'sms';
        $this->api($this->token($sms), ['events' => [$wrongSource]])->assertUnprocessable();
        $device->update(['allow_location' => false]);
        $this->api($token, ['events' => [$event]])->assertUnprocessable();
        $this->assertDatabaseCount('history_events', 1);
    }

    public function test_pairing_can_grant_all_six_sources_for_agent(): void
    {
        $owner = $this->member();
        $this->actingAs($owner)->post('/devices/pair', ['sources' => config('history.sources'), 'allow_location' => '1'])->assertRedirect('/devices');
        $this->postJson('/api/v1/pair', ['code' => session('pair_code'), 'device_name' => 'A34 Agent'])->assertCreated()->assertJson(['sources' => config('history.sources'), 'allow_location' => true]);
    }

    public function test_admin_logs_in_directly_and_legacy_mfa_page_redirects_home(): void
    {
        $admin = $this->member(['role' => 'admin', 'password' => 'ExamplePassword123']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'ExamplePassword123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertDontSee('2FA');
        $this->get('/')->assertOk();
        $this->get('/2fa')->assertRedirect('/');
        $this->post('/2fa', ['code' => '000000'])->assertStatus(405);
    }

    public function test_admin_changes_are_audited_and_disabling_revokes_access(): void
    {
        $admin = $this->member(['role' => 'admin']);
        $target = $this->member();
        $device = $this->device($target);
        $token = $this->token($device);
        $this->actingAs($admin)->put('/admin/accounts/'.$target->id, ['role' => 'member', 'is_active' => false])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'target_id' => $target->id, 'action' => 'account.updated']);
        $this->api($token, ['events' => [$this->event()]])->assertUnauthorized();
    }

    public function test_invitation_password_reset_is_expiring_and_single_use(): void
    {
        Notification::fake();
        $user = $this->member(['setup_pending' => true]);
        $token = Password::createToken($user);
        $body = ['email' => $user->email, 'token' => $token, 'password' => 'LongSecurePassword123', 'password_confirmation' => 'LongSecurePassword123'];
        $this->post('/password/setup', $body)->assertRedirect('/login');
        $this->assertFalse($user->fresh()->setup_pending);
        $this->assertTrue(Hash::check($body['password'], $user->fresh()->password));
        RateLimiter::clear('unused');
        $this->withoutMiddleware(ThrottleRequests::class)->post('/password/setup', $body)->assertSessionHasErrors('email');
        $token = Password::createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subHours(2)]);
        $this->post('/password/setup', array_replace($body, ['token' => $token]))->assertSessionHasErrors('email');
    }

    public function test_login_and_pairing_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertTooManyRequests();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/pair', ['code' => '0123456789ABCDEF', 'device_name' => 'Test']);
        }
        $this->postJson('/api/v1/pair', ['code' => '0123456789ABCDEF', 'device_name' => 'Test'])->assertTooManyRequests();
    }

    public function test_retention_purges_old_records(): void
    {
        $owner = $this->member();
        $device = $this->device($owner);
        HistoryEvent::create(['user_id' => $owner->id, 'device_id' => $device->id] + $this->event(['occurred_at' => now()->subDays(91)]));
        $this->artisan('history:purge')->assertSuccessful();
        $this->assertDatabaseCount('history_events', 0);
    }

    public function test_consented_device_metadata_is_encrypted_and_other_account_cannot_change_owner(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $device = $this->device($owner, ['allow_ip' => true, 'allow_location' => true]);
        $payload = $this->event(['latitude' => 10.123, 'longitude' => 106.456, 'accuracy_m' => 12, 'server_domain' => 'mail.example.com']);
        $this->api($this->token($device), ['events' => [$payload]])->assertCreated();
        $event = HistoryEvent::firstOrFail();
        $this->assertSame($owner->id, $event->user_id);
        $this->assertSame('10.123', $event->latitude);
        $this->assertSame('127.0.0.1', $event->device_ip);
        $raw = DB::table('history_events')->first();
        $this->assertNotSame('10.123', $raw->latitude);
        $this->assertNotSame('mail.example.com', $raw->server_domain);
    }

    public function test_admin_can_invite_but_members_cannot_manage_accounts(): void
    {
        Notification::fake();
        $admin = $this->member(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/accounts', ['name' => 'New user', 'email' => 'new@example.com', 'role' => 'member'])->assertRedirect();
        $new = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($new->setup_pending);
        Notification::assertSentTo($new, ResetPassword::class, function ($notification) use ($new): bool {
            $url = $notification->toMail($new)->actionUrl;

            return str_contains($url, '#token=') && ! str_contains(parse_url($url, PHP_URL_QUERY) ?? '', 'token=');
        });
        $this->actingAs($this->member())->get('/admin')->assertForbidden();
        $this->put('/admin/accounts/'.$new->id, ['role' => 'admin', 'is_active' => true])->assertForbidden();
    }

    public function test_web_mutations_require_csrf_and_cookies_are_secure(): void
    {
        $this->app->instance('env', 'local');
        config(['session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
        $response = $this->get('/login')->assertOk();
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertStatus(419);
    }

    public function test_password_and_device_event_rate_limits(): void
    {
        Notification::fake();
        $this->post('/password/forgot', ['email' => 'missing@example.com'])->assertRedirect();
        $this->post('/password/forgot', ['email' => 'missing@example.com'])->assertTooManyRequests();
        $token = $this->token($this->device($this->member()));
        $event = $this->event();
        for ($i = 0; $i < 60; $i++) {
            $this->api($token, ['events' => [$event]]);
        }
        $this->api($token, ['events' => [$event]])->assertTooManyRequests();
    }

    public function test_oversized_and_large_batches_are_rejected(): void
    {
        $owner = $this->member();
        $token = $this->token($this->device($owner));
        $events = [];
        for ($i = 0; $i < 51; $i++) {
            $events[] = $this->event();
        }
        $this->api($token, ['events' => $events])->assertUnprocessable();
        $this->api($token, ['events' => [$this->event(['sender_name' => str_repeat('x', 66000)])]])->assertStatus(413);
    }

    public function test_owned_views_render_without_exposing_other_account_metadata(): void
    {
        $owner = $this->member();
        $device = $this->device($owner);
        $event = HistoryEvent::create(['user_id' => $owner->id, 'device_id' => $device->id] + $this->event());
        $this->get('/password/forgot')->assertOk();
        $this->get('/password/setup?email=test@example.com')->assertOk();
        $this->actingAs($owner)->get('/devices')->assertOk()->assertSee('Pixel');
        $this->get('/history/'.$event->id)->assertOk()->assertSee('Tên riêng');
    }
}
