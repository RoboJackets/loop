<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\BankTransaction;
use App\Models\DocuSignEnvelope;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use App\Models\ExternalCommitteeMember;
use App\Models\FundingAllocation;
use App\Models\FundingAllocationLine;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Imports all models into Scout.
 *
 * @phan-suppress PhanUnreferencedClass
 */
class ImportAllModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:import-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all supported models into the search index';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->call('scout:import', [
            'model' => BankTransaction::class,
        ]);
        $this->call('scout:import', [
            'model' => DocuSignEnvelope::class,
        ]);
        $this->call('scout:import', [
            'model' => EngagePurchaseRequest::class,
        ]);
        $this->call('scout:import', [
            'model' => ExpenseReport::class,
        ]);
        $this->call('scout:import', [
            'model' => ExpenseReportLine::class,
        ]);
        $this->call('scout:import', [
            'model' => ExternalCommitteeMember::class,
        ]);
        $this->call('scout:import', [
            'model' => FundingAllocation::class,
        ]);
        $this->call('scout:import', [
            'model' => FundingAllocationLine::class,
        ]);
        $this->call('scout:import', [
            'model' => User::class,
        ]);
        Attachment::all()->each(static function (Attachment $attachment, int $key): void {
            $attachment->searchable();
        });
        EmailRequest::all()->each(static function (EmailRequest $emailRequest, int $key): void {
            $emailRequest->searchable();
        });

        return Command::SUCCESS;
    }
}
