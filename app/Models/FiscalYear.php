<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fiscal year.
 *
 * @property int $id
 * @property int $ending_year
 * @property bool $in_scope_for_quickbooks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\DocuSignEnvelope> $envelopes
 * @property-read int|null $envelopes_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\FundingAllocation> $fundingAllocations
 * @property-read int|null $funding_allocations_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\ExpenseReport> $expenseReports
 * @property-read int|null $expense_reports_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmailRequest> $emailRequests
 * @property-read int|null $email_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EngagePurchaseRequest> $engagePurchaseRequests
 * @property-read int|null $engage_purchase_requests_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear query()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereEndingYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereInScopeForQuickbooks($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class FiscalYear extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     *
     * @phan-read-only
     */
    protected $casts = [
        'in_scope_for_quickbooks' => 'boolean',
    ];

    /**
     * Get the funding allocations for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FundingAllocation>
     */
    public function fundingAllocations(): HasMany
    {
        return $this->hasMany(FundingAllocation::class);
    }

    /**
     * Get the DocuSign envelopes for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\DocuSignEnvelope>
     */
    public function envelopes(): HasMany
    {
        return $this->hasMany(DocuSignEnvelope::class);
    }

    /**
     * Get the Engage purchase requests for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\EngagePurchaseRequest>
     */
    public function engagePurchaseRequests(): HasMany
    {
        return $this->hasMany(EngagePurchaseRequest::class);
    }

    /**
     * Get the email requests for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\EmailRequest>
     */
    public function emailRequests(): HasMany
    {
        return $this->hasMany(EmailRequest::class);
    }

    /**
     * Get the expense reports for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExpenseReport>
     */
    public function expenseReports(): HasMany
    {
        return $this->hasMany(ExpenseReport::class);
    }

    public static function fromDate(Carbon $date): self
    {
        return self::where('ending_year', self::intFromDate($date))->sole();
    }

    public static function intFromDate(Carbon $date): int
    {
        return $date->year + ($date->month < 7 ? 0 : 1);
    }
}
