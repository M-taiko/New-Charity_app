<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Register storage_url Blade directive
        \Blade::directive('storageUrl', function ($path) {
            return "<?php echo storage_url($path); ?>";
        });
    }
}
