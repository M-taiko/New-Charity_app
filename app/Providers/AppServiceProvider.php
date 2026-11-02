<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login rate limit — configurable per environment (LOGIN_THROTTLE_MAX).
        // Production keeps a strict default; local dev / e2e suites raise it.
        RateLimiter::for('login', function (Request $request) {
            $max = max(2, (int) env('LOGIN_THROTTLE_MAX', 10));

            return Limit::perMinute($max)->by($request->input('email') . '|' . $request->ip());
        });

        // Polyfill for mb_split if not available
        if (!function_exists('mb_split')) {
            function mb_split($pattern, $string, $limit = -1) {
                return preg_split('/' . $pattern . '/u', $string, $limit);
            }
        }

        // Register storage_url Blade directive
        \Blade::directive('storageUrl', function ($path) {
            return "<?php echo storage_url($path); ?>";
        });

        // Authorization Gates
        Gate::define('manage_expense_items', function ($user) {
            return $user->hasRole('محاسب') || $user->hasRole('مدير');
        });

        Gate::define('view_all_expenses', function ($user) {
            return $user->hasRole('محاسب') || $user->hasRole('مدير') || $user->hasRole('مشرف');
        });

        Gate::define('view_all_records', function ($user) {
            return $user->hasRole('محاسب') || $user->hasRole('مدير') || $user->hasRole('مشرف');
        });

        Gate::define('manage_social_cases', function ($user) {
            return $user->hasRole('محاسب') || $user->hasRole('مدير') || $user->hasRole('باحث اجتماعي');
        });
    }
}
