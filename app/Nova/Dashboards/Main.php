<?php

declare(strict_types=1);

namespace App\Nova\Dashboards;

use App\Nova\Metrics\AverageDaysToApproveExpenseReport;
use App\Nova\Metrics\AverageDaysToCreateExpenseReport;
use App\Nova\Metrics\AverageDaysToPayExpenseReport;
use App\Nova\Metrics\AverageDaysToReconcileExpensePayment;
use App\Nova\Metrics\EngagePurchaseRequestsMissingExpenseReports;
use App\Nova\Metrics\ExpensePaymentsInProgress;
use App\Nova\Metrics\ExpensePaymentsPendingReconciliation;
use App\Nova\Metrics\ExpenseReportsInProgress;
use App\Nova\Metrics\ExpenseReportsPendingApproval;
use App\Nova\Metrics\ExpenseReportsPendingPayment;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /**
     * Get the displayable name of the dashboard.
     */
    #[\Override]
    public function name(): string
    {
        return 'Home';
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array<\Laravel\Nova\Card>
     */
    #[\Override]
    public function cards(): array
    {
        return [
            EngagePurchaseRequestsMissingExpenseReports::make()
                ->width('1/4'),
            ExpenseReportsPendingApproval::make()
                ->width('1/4'),
            ExpenseReportsPendingPayment::make()
                ->width('1/4'),
            ExpensePaymentsPendingReconciliation::make()
                ->width('1/4'),
            AverageDaysToCreateExpenseReport::make()
                ->width('1/4'),
            AverageDaysToApproveExpenseReport::make()
                ->width('1/4'),
            AverageDaysToPayExpenseReport::make()
                ->width('1/4'),
            AverageDaysToReconcileExpensePayment::make()
                ->width('1/4'),
            ExpenseReportsInProgress::make()
                ->width('1/2'),
            ExpensePaymentsInProgress::make()
                ->width('1/2'),
        ];
    }
}
