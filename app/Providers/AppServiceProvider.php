<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
    }
}
