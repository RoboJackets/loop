<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attachment;
use App\Models\BankTransaction;
use App\Models\DocuSignEnvelope;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use App\Observers\AttachmentObserver;
use App\Observers\BankTransactionObserver;
use App\Observers\ExpenseReportLineObserver;
use App\Observers\ExpenseReportObserver;
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
            'engage-purchase-request' => EngagePurchaseRequest::class,
            'expense-report-line' => ExpenseReportLine::class,
        ]);

        Attachment::observe(AttachmentObserver::class);
        BankTransaction::observe(BankTransactionObserver::class);
        ExpenseReport::observe(ExpenseReportObserver::class);
        ExpenseReportLine::observe(ExpenseReportLineObserver::class);
    }
}
