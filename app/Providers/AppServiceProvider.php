<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DocuSignEnvelope;
use App\Models\ExpenseReportLine;
use Illuminate\Database\Eloquent\Relations\Relation;
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
            'expense-report-line' => ExpenseReportLine::class,
        ]);
    }
}
