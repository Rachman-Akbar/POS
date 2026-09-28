<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\View;
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
        $this->shareAppearanceWithTheAppShell();
    }

    /**
     * Resolve the admin default theme on the server so the first paint is correct.
     *
     * The settings table may not exist yet (fresh install, tests that do not
     * migrate), so fall back to the built-in defaults instead of failing.
     */
    private function shareAppearanceWithTheAppShell(): void
    {
        View::composer('app', function ($view) {
            $appearance = rescue(
                fn (): array => Setting::appearance(),
                Setting::defaultAppearance(),
                report: false,
            );

            $view->with('themeMode', $appearance['mode']);
            $view->with('themeAccent', $appearance['accent']);
        });
    }
}
