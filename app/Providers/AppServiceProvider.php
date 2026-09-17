<?php

namespace App\Providers;

use App\Services\SiteContentService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteContentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Don't announce the backend language/version in the response
        // headers — works regardless of how the host runs PHP (mod_php,
        // FPM, CGI), matching the original site's partials/head.php.
        header_remove('X-Powered-By');

        // Available as $siteContent in every Blade view/component, so pages
        // and components can call e.g. $siteContent->eventPhase(...) without
        // each controller having to inject and pass it manually.
        View::share('siteContent', $this->app->make(SiteContentService::class));

        // @icon('arrow') / @icon('arrow', 'class="nav__caret"') — direct
        // equivalent of the original svg() helper, usable inline the same
        // way the old raw-PHP echo was (e.g. inside a button's text).
        Blade::directive('icon', function ($expression) {
            return '<?php echo \App\Support\Site\Icons::render('.$expression.'); ?>';
        });
    }
}
