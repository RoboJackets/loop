<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReportLine;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // nothing to do here, yet
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'docusign-envelope' => DocuSignEnvelope::class,
            'email-request' => EmailRequest::class,
            'engage-purchase-request' => EngagePurchaseRequest::class,
            'expense-report-line' => ExpenseReportLine::class,
        ]);

        Model::shouldBeStrict();

        // Lazy-loading needs to be allowed for console commands due to https://github.com/laravel/scout/issues/462
        if ($this->app->runningInConsole()) {
            Model::preventLazyLoading(false);
        }

        $this->bootRoute();
    }

    public function bootRoute(): void
    {
        RateLimiter::for(
            'api',
            static fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->id ?? $request->ip())
        );
    }
}
