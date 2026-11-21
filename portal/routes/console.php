<?php

use App\Models\HistoryEvent;
use App\Models\PairingCode;
use App\Models\User;
use App\Services\Audit;
use App\Services\InvitationMailer;
use App\Services\PhoneIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

Artisan::command('portal:bootstrap-admin {email} {name}', function (): int {
    if (User::where('role', 'admin')->exists()) {
        $this->error('An administrator already exists.');

        return 1;
    }
    $email = (string) $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $this->error('Invalid email.');

        return 1;
    }
    $user = User::create(['name' => $this->argument('name'), 'email' => $email, 'password' => Str::random(64), 'role' => 'admin', 'is_active' => true, 'setup_pending' => true]);
    Audit::record(null, 'admin.bootstrapped', 'user', $user->id);
    if (! app(InvitationMailer::class)->send($user)) {
        $this->error('Administrator created, but email was not sent. Configure SMTP, then request a password link from the portal.');

        return 1;
    }
    $this->info('Administrator created; setup link sent through configured mail transport.');

    return 0;
});
Artisan::command('portal:reset-mfa {email} {--force}', function (): int {
    if (! $this->option('force')) {
        $this->error('Requires explicit --force from the server operator.');

        return 1;
    }
    $user = User::where('email', $this->argument('email'))->where('role', 'admin')->firstOrFail();
    $user->update(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_step' => null]);
    DB::table('sessions')->where('user_id', $user->id)->delete();
    Audit::record(null, 'mfa.operator_reset', 'user', $user->id);
    $this->info('MFA reset; portal access requires enrollment again.');

    return 0;
});
Artisan::command('history:purge', function (): int {
    $days = (int) config('history.retention_days');
    if ($days < 1 || $days > 3650) {
        $this->error('Retention must be between 1 and 3650 days.');

        return 1;
    }
    $deleted = HistoryEvent::where('occurred_at', '<', now()->subDays($days))->delete();
    PairingCode::where('expires_at', '<', now()->subDay())->delete();
    PersonalAccessToken::where('expires_at', '<', now())->delete();
    $this->info('Purged records: '.$deleted);

    return 0;
});
Artisan::command('portal:preflight', function (PhoneIndex $index): int {
    $fail = [];
    if (! app()->isProduction()) {
        $fail[] = 'APP_ENV must be production';
    }
    if (config('app.debug')) {
        $fail[] = 'APP_DEBUG must be false';
    }
    if (! str_starts_with((string) config('app.url'), 'https://')) {
        $fail[] = 'APP_URL must use HTTPS';
    }
    if (config('database.default') !== 'mysql') {
        $fail[] = 'MySQL is required';
    }
    if (! config('session.secure') || ! config('session.http_only') || ! config('session.encrypt') || config('session.same_site') !== 'lax') {
        $fail[] = 'Secure session configuration required';
    }
    if (in_array(config('mail.default'), ['log', 'array'], true)) {
        $fail[] = 'Use real SMTP; invitation links must never be logged';
    }
    if (! config('app.key')) {
        $fail[] = 'APP_KEY is missing';
    }
    try {
        $index->hash('+84901234567');
    } catch (Throwable) {
        $fail[] = 'HISTORY_INDEX_KEY missing or invalid';
    }
    if (config('history.index_key') === str_replace('base64:', '', (string) config('app.key'))) {
        $fail[] = 'HMAC key must differ from APP_KEY';
    }
    foreach (['retention_days' => 3650, 'backup_retention_days' => 30, 'token_days' => 90] as $key => $max) {
        if (config('history.'.$key) < 1 || config('history.'.$key) > $max) {
            $fail[] = 'Invalid history.'.$key;
        }
    }
    foreach ($fail as $error) {
        $this->error($error);
    }
    if ($fail) {
        return 1;
    }
    DB::connection()->getPdo();
    $this->info('Configuration checks passed. Verify web PHP, document root, HTTPS, SMTP and backup policy separately.');

    return 0;
});
Schedule::command('history:purge')->dailyAt('02:00')->timezone('Asia/Ho_Chi_Minh')->withoutOverlapping();
