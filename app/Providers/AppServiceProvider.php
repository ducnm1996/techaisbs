<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        $resetUrl = fn (User $user, string $token) => rtrim(config('app.url'), '/').'/password/setup?'.http_build_query(['email' => $user->email]).'#token='.rawurlencode($token);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Thiết lập mật khẩu TechAI')
            ->greeting('Xin chào '.$user->name)
            ->line('Bạn nhận được liên kết thiết lập hoặc đặt lại mật khẩu cho tài khoản TechAI.')
            ->action('Thiết lập mật khẩu', $resetUrl($user, $token))
            ->line('Liên kết dùng một lần và hết hạn sau 60 phút. Nếu bạn không yêu cầu, hãy bỏ qua email này.'));
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(10)->by('login-ip:'.$r->ip()), Limit::perMinute(5)->by('login:'.hash('sha256', mb_strtolower(trim((string) $r->input('email')))))]);
        RateLimiter::for('password', fn (Request $r) => [Limit::perMinute(3)->by('password-ip:'.$r->ip()), Limit::perMinute(1)->by('password:'.hash('sha256', strtolower((string) $r->input('email'))))]);
        RateLimiter::for('pair', fn (Request $r) => Limit::perMinute(5)->by('pair:'.$r->ip()));
        RateLimiter::for('device-events', fn (Request $r) => Limit::perMinute(60)->by('device:'.$r->user()?->id));
        RateLimiter::for('pair-create', fn (Request $r) => Limit::perMinute(3)->by('pair-create:'.$r->user('web')?->id));
    }
}
